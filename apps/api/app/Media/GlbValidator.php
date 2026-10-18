<?php

namespace App\Media;

use Illuminate\Validation\ValidationException;

/** Pure, bounded parsing only: no shell, GPU, remote fetch, plugins or native model importer. */
class GlbValidator
{
    public const MAX_BYTES = 8388608;

    private array $json;

    private string $bin;

    private int $instances = 0;

    private int $instanceTriangles = 0;

    private int $instanceDrawCalls = 0;

    private array $bounds;

    private int $started;

    private int $memory;

    public function validate(string $bytes): array
    {
        try {
            return $this->validateAsset($bytes);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['model_upload' => 'Malformed GLB structure.']);
        }
    }

    private function validateAsset(string $bytes): array
    {
        $this->started = hrtime(true);
        $this->memory = memory_get_usage(true);
        $this->instances = 0;
        $this->instanceTriangles = 0;
        $this->instanceDrawCalls = 0;
        $this->bounds = [[INF, INF, INF], [-INF, -INF, -INF]];
        $this->require(strlen($bytes) >= 28 && strlen($bytes) <= min(self::MAX_BYTES, config('viewer.max_bytes')), 'GLB must be at most 8 MiB.');
        $h = unpack('Vmagic/Vversion/Vlength/VjsonLength/VjsonType', substr($bytes, 0, 20));
        $this->require($h['magic'] === 0x46546C67 && $h['version'] === 2 && $h['length'] === strlen($bytes), 'Invalid GLB 2 header or length.');
        $n = $h['jsonLength'];
        $this->require($h['jsonType'] === 0x4E4F534A && $n > 0 && $n <= min(1048576, config('viewer.max_json_bytes')) && $n % 4 === 0 && $n + 28 <= strlen($bytes), 'Invalid bounded JSON chunk.');
        try {
            $parsed = json_decode(substr($bytes, 20, $n), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->require(false, 'Malformed glTF JSON.');
        }
        $this->require(is_array($parsed) && ! array_is_list($parsed), 'glTF root must be an object.');
        $this->json = $parsed;
        $b = unpack('Vlength/Vtype', substr($bytes, 20 + $n, 8));
        $this->require($b['type'] === 0x004E4942 && $b['length'] % 4 === 0 && $n + 28 + $b['length'] === strlen($bytes), 'Exactly one embedded BIN chunk is required.');
        $this->bin = substr($bytes, 28 + $n);
        $j = &$this->json;
        $this->require(! array_diff(array_keys($j), ['asset', 'scene', 'scenes', 'nodes', 'meshes', 'buffers', 'bufferViews', 'accessors', 'materials', 'textures', 'images', 'samplers', 'extensionsUsed', 'extensionsRequired', 'extras', 'animations', 'skins', 'cameras']), 'Unsupported glTF root property.');
        $this->require(($j['asset']['version'] ?? null) === '2.0' && ! isset($j['asset']['minVersion']), 'Only glTF 2.0 is supported.');
        foreach (['animations', 'skins', 'cameras'] as $key) {
            $this->require(empty($j[$key]), 'Animated, skinned and camera assets are unsupported.');
        }
        $this->extensions($j);
        foreach (['extensionsUsed', 'extensionsRequired'] as $key) {
            $this->require(is_array($j[$key] ?? []) && array_is_list($j[$key] ?? []) && count($j[$key] ?? []) <= 2, 'Invalid extension declaration.');
            foreach ($j[$key] ?? [] as $ext) {
                $this->require(in_array($ext, config('viewer.extensions'), true), 'Unsupported glTF extension.');
            }
        }
        $this->require(! array_diff($j['extensionsRequired'] ?? [], $j['extensionsUsed'] ?? []), 'Required extensions must be declared as used.');
        $buffers = $this->collection('buffers', 1, true);
        $this->require(count($buffers) === 1 && is_int($buffers[0]['byteLength'] ?? null) && $buffers[0]['byteLength'] > 0 && $buffers[0]['byteLength'] <= strlen($this->bin) && strlen($this->bin) - $buffers[0]['byteLength'] <= 3, 'Invalid embedded buffer.');
        $views = $this->collection('bufferViews', 256, true);
        foreach ($views as $v) {
            $this->require(($v['buffer'] ?? null) === 0 && is_int($v['byteLength'] ?? null) && $v['byteLength'] > 0 && is_int($v['byteOffset'] ?? 0) && ($v['byteOffset'] ?? 0) >= 0 && ($v['byteOffset'] ?? 0) + $v['byteLength'] <= $buffers[0]['byteLength'], 'Invalid buffer view range.');
            if (isset($v['byteStride'])) {
                $this->require(is_int($v['byteStride']) && $v['byteStride'] >= 4 && $v['byteStride'] <= 252 && $v['byteStride'] % 4 === 0, 'Invalid vertex stride.');
            }
            if (isset($v['target'])) {
                $this->require(in_array($v['target'], [34962, 34963], true), 'Invalid buffer target.');
            }
        }
        $accessors = $this->collection('accessors', min(256, config('viewer.max_accessors')), true);
        $decoded = 0;
        foreach ($accessors as $a) {
            $this->require(! isset($a['sparse']) && is_int($a['count'] ?? null) && $a['count'] > 0 && $a['count'] <= 300000 && in_array($a['type'] ?? '', ['SCALAR', 'VEC2', 'VEC3', 'VEC4'], true) && in_array($a['componentType'] ?? 0, [5121, 5123, 5125, 5126], true), 'Unsupported accessor.');
            $v = $this->ref('bufferViews', $a['bufferView'] ?? null);
            $components = ['SCALAR' => 1, 'VEC2' => 2, 'VEC3' => 3, 'VEC4' => 4][$a['type']];
            $size = [5121 => 1, 5123 => 2, 5125 => 4, 5126 => 4][$a['componentType']];
            $decoded += $components * $a['count'];
            $this->require($decoded <= min(1500000, config('viewer.max_decoded_components')) && (! isset($a['normalized']) || is_bool($a['normalized'])), 'Decoded accessor budget or flag invalid.');
            $offset = $a['byteOffset'] ?? 0;
            $stride = $v['byteStride'] ?? $components * $size;
            $this->require(is_int($offset) && $offset >= 0 && $offset % $size === 0 && (($v['byteOffset'] ?? 0) + $offset) % $size === 0 && $stride >= $components * $size && $offset + ($a['count'] - 1) * $stride + $components * $size <= $v['byteLength'], 'Accessor exceeds buffer or alignment.');
            // Read every component, including unused declarations: reject NaN/infinite values.
            $mins = array_fill(0, $components, INF);
            $maxs = array_fill(0, $components, -INF);
            for ($i = 0; $i < $a['count']; $i++) {
                foreach ($this->value($a, $i) as $axis => $value) {
                    $this->require(is_finite($value) && abs($value) <= 100000, 'Non-finite or extreme accessor value.');
                    $mins[$axis] = min($mins[$axis], $value);
                    $maxs[$axis] = max($maxs[$axis], $value);
                }
            }
            if (isset($a['min']) || isset($a['max'])) {
                foreach (['min' => $mins, 'max' => $maxs] as $key => $actual) {
                    $this->vector($a[$key] ?? null, $components, -100000, 100000);
                    foreach ($actual as $axis => $v) {
                        $this->require(abs($a[$key][$axis] - $v) <= .00001 * max(1, abs($v)), 'Accessor declared bounds do not match its contents.');
                    }
                }
            }
        }
        $images = $this->collection('images', min(8, config('viewer.max_texture_count')));
        $pixels = 0;
        foreach ($images as $image) {
            $v = $this->ref('bufferViews', $image['bufferView'] ?? null);
            $this->require(! isset($v['byteStride']), 'Image buffer cannot be interleaved.');
            $data = substr($this->bin, $v['byteOffset'] ?? 0, $v['byteLength']);
            $info = @getimagesizefromstring($data);
            $this->require($info && in_array($image['mimeType'] ?? '', ['image/jpeg', 'image/png'], true) && $info['mime'] === $image['mimeType'] && $info[0] >= 1 && $info[1] >= 1 && max($info[0], $info[1]) <= min(2048, config('viewer.max_texture_dimension')), 'Embedded images must be valid JPEG/PNG up to 2048px.');
            $decodedImage = @imagecreatefromstring($data);
            $this->require($decodedImage !== false, 'Embedded image failed full decoding.');
            imagedestroy($decodedImage);
            $pixels += $info[0] * $info[1];
            $this->require($pixels <= min(8388608, config('viewer.max_texture_pixels')), 'Embedded texture pixel budget exceeded.');
        }
        foreach ($this->collection('samplers', 16) as $s) {
            foreach (['wrapS', 'wrapT'] as $k) {
                if (isset($s[$k])) {
                    $this->require(in_array($s[$k], [33071, 33648, 10497], true), 'Unsupported sampler.');
                }
            }
            foreach (['minFilter', 'magFilter'] as $k) {
                if (isset($s[$k])) {
                    $this->require(in_array($s[$k], $k === 'magFilter' ? [9728, 9729] : [9728, 9729, 9984, 9985, 9986, 9987], true), 'Unsupported texture filter.');
                }
            }
        }
        foreach ($this->collection('textures', min(8, config('viewer.max_texture_count'))) as $t) {
            $this->ref('images', $t['source'] ?? null);
            if (isset($t['sampler'])) {
                $this->ref('samplers', $t['sampler']);
            }
        }
        foreach ($this->collection('materials', 32) as $material) {
            $this->material($material);
        }
        $vertices = 0;
        $triangles = 0;
        $primitives = 0;
        foreach ($this->collection('meshes', 64, true) as $mesh) {
            $this->require(is_array($mesh['primitives'] ?? null) && array_is_list($mesh['primitives']) && count($mesh['primitives']) > 0 && count($mesh['primitives']) <= 32 && empty($mesh['weights']), 'Invalid static mesh.');
            foreach ($mesh['primitives'] as $primitive) {
                $this->require(($primitive['mode'] ?? 4) === 4 && empty($primitive['targets']), 'Only static triangle primitives are supported.');
                $attrs = $primitive['attributes'] ?? [];
                $this->require(is_array($attrs) && ! array_diff(array_keys($attrs), ['POSITION', 'NORMAL', 'TEXCOORD_0', 'TEXCOORD_1', 'TANGENT', 'COLOR_0']), 'Unsupported vertex attributes.');
                $position = $this->ref('accessors', $attrs['POSITION'] ?? null);
                $this->require($position['type'] === 'VEC3' && $position['componentType'] === 5126 && empty($position['normalized']), 'Positions must be float VEC3.');
                $this->require(isset($position['min'],$position['max']), 'Position bounds are required.');
                foreach ($attrs as $semantic => $index) {
                    $a = $this->ref('accessors', $index);
                    $type = match ($semantic) {
                        'POSITION', 'NORMAL' => ['VEC3'], 'TEXCOORD_0', 'TEXCOORD_1' => ['VEC2'], 'TANGENT' => ['VEC4'], default => ['VEC3', 'VEC4']
                    };
                    $this->require($a['count'] === $position['count'] && in_array($a['type'], $type, true), 'Attribute layout/count mismatch.');
                    if (in_array($semantic, ['NORMAL', 'TANGENT'], true)) {
                        $this->require($a['componentType'] === 5126, 'Normals/tangents must be floats.');
                    }
                }
                $count = $position['count'];
                if (isset($primitive['indices'])) {
                    $a = $this->ref('accessors', $primitive['indices']);
                    $this->require($a['type'] === 'SCALAR' && in_array($a['componentType'], [5121, 5123, 5125], true) && empty($a['normalized']), 'Invalid indices.');
                    $count = $a['count'];
                    for ($i = 0; $i < $count; $i++) {
                        $this->require($this->value($a, $i)[0] < $position['count'], 'Index exceeds vertex range.');
                    }
                }
                if (isset($primitive['material'])) {
                    $this->ref('materials', $primitive['material']);
                }
                $this->require($count % 3 === 0, 'Triangle index count must be divisible by three.');
                $vertices += $position['count'];
                $triangles += $count / 3;
                $primitives++;
                $this->require($vertices <= min(100000, config('viewer.max_vertices')) && $triangles <= min(100000, config('viewer.max_triangles')) && $primitives <= min(64, config('viewer.max_primitives')), 'Mesh complexity budget exceeded.');
            }
        }
        $nodes = $this->collection('nodes', min(128, config('viewer.max_nodes')), true);
        $scenes = $this->collection('scenes', 1, true);
        $this->require(count($scenes) === 1 && ($j['scene'] ?? 0) === 0 && is_array($scenes[0]['nodes'] ?? null) && array_is_list($scenes[0]['nodes']), 'Exactly one static scene is required.');
        $seen = [];
        $identity = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1];
        foreach ($scenes[0]['nodes'] as $index) {
            $this->require(is_int($index), 'Invalid scene node reference.');
            $this->node($index, $identity, $seen, 0);
        }
        $this->require(count($seen) === count($nodes) && $this->instances > 0, 'Unreachable nodes or empty scene.');
        $dims = [];
        foreach (['width', 'height', 'thickness'] as $axis => $name) {
            $value = $this->bounds[1][$axis] - $this->bounds[0][$axis];
            $this->require(is_finite($value) && $value >= 0 && $value <= 100, 'Model scene bounds exceed the supported range.');
            $dims[$name] = $value;
        }
        $this->require(max($dims) > 0.000001, 'Empty or degenerate model.');
        // Remove opaque metadata from the public derivative; original remains private.
        $this->strip($j);
        $json = json_encode($j, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
        $derivative = pack('V*', 0x46546C67, 2, 28 + strlen($json) + strlen($this->bin), strlen($json), 0x4E4F534A).$json.pack('V*', strlen($this->bin), 0x004E4942).$this->bin;
        $this->require(strlen($derivative) <= min(self::MAX_BYTES, config('viewer.max_bytes')), 'Validated GLB derivative exceeds 8 MiB.');

        return ['bytes' => $derivative, 'metadata' => ['validator_version' => config('viewer.validator_version'), 'sha256' => hash('sha256', $derivative), 'vertices' => $vertices, 'triangles' => $triangles, 'draw_calls' => $this->instanceDrawCalls, 'instanced_triangles' => $this->instanceTriangles, 'instanced_vertices' => $this->instances, 'materials' => count($j['materials'] ?? []), 'textures' => count($j['textures'] ?? []), 'texture_pixels' => $pixels, 'scene_bounds_m' => $dims]];
    }

    private function collection(string $key, int $max, bool $required = false): array
    {
        $a = $this->json[$key] ?? [];
        $this->require(is_array($a) && array_is_list($a) && count($a) <= $max && (! $required || count($a) > 0), 'Invalid or excessive '.$key.'.');
        foreach ($a as $v) {
            $this->require(is_array($v), 'Invalid glTF object.');
        }

        return $a;
    }

    private function ref(string $key, mixed $index): array
    {
        $this->require(is_int($index) && $index >= 0 && isset($this->json[$key][$index]) && is_array($this->json[$key][$index]), 'Invalid glTF reference.');

        return $this->json[$key][$index];
    }

    private function value(array $a, int $index): array
    {
        $v = $this->json['bufferViews'][$a['bufferView']];
        $count = ['SCALAR' => 1, 'VEC2' => 2, 'VEC3' => 3, 'VEC4' => 4][$a['type']];
        [$size, $format] = match ($a['componentType']) {
            5121 => [1, 'C'], 5123 => [2, 'v'], 5125 => [4, 'V'], 5126 => [4, 'g']
        };
        $offset = ($v['byteOffset'] ?? 0) + ($a['byteOffset'] ?? 0) + $index * ($v['byteStride'] ?? $size * $count);

        return array_values(unpack($format.'*', substr($this->bin, $offset, $size * $count)));
    }

    private function material(array $m): void
    {
        $this->require(! array_diff(array_keys($m), ['name', 'pbrMetallicRoughness', 'normalTexture', 'occlusionTexture', 'emissiveTexture', 'emissiveFactor', 'alphaMode', 'alphaCutoff', 'doubleSided', 'extensions', 'extras']), 'Unsupported material property.');
        $pbr = $m['pbrMetallicRoughness'] ?? [];
        $this->require(is_array($pbr) && ! array_diff(array_keys($pbr), ['baseColorFactor', 'baseColorTexture', 'metallicFactor', 'roughnessFactor', 'metallicRoughnessTexture', 'extensions', 'extras']), 'Invalid PBR material.');
        $this->require(in_array($m['alphaMode'] ?? 'OPAQUE', ['OPAQUE', 'MASK', 'BLEND'], true), 'Invalid alpha mode.');
        foreach (['metallicFactor', 'roughnessFactor'] as $key) {
            if (isset($m['pbrMetallicRoughness'][$key])) {
                $this->scalar($m['pbrMetallicRoughness'][$key], 0, 1);
            }
        }
        foreach (['baseColorFactor' => 4, 'emissiveFactor' => 3] as $key => $n) {
            $v = $key === 'baseColorFactor' ? ($m['pbrMetallicRoughness'][$key] ?? null) : ($m[$key] ?? null);
            if ($v !== null) {
                $this->vector($v, $n, 0, 1);
            }
        }
        foreach (['baseColorTexture', 'metallicRoughnessTexture'] as $key) {
            if (isset($m['pbrMetallicRoughness'][$key])) {
                $this->texture($m['pbrMetallicRoughness'][$key]);
            }
        }
        foreach (['normalTexture', 'occlusionTexture', 'emissiveTexture'] as $key) {
            if (isset($m[$key])) {
                $this->texture($m[$key]);
                foreach (['scale', 'strength'] as $s) {
                    if (isset($m[$key][$s])) {
                        $this->scalar($m[$key][$s], 0, 1);
                    }
                }
            }
        }
        if (isset($m['alphaCutoff'])) {
            $this->scalar($m['alphaCutoff'], 0, 1);
        }
        if (isset($m['doubleSided'])) {
            $this->require(is_bool($m['doubleSided']), 'Invalid material flag.');
        }
        foreach ($m['extensions']['KHR_materials_clearcoat'] ?? [] as $key => $v) {
            $this->require(in_array($key, ['clearcoatFactor', 'clearcoatRoughnessFactor', 'clearcoatTexture', 'clearcoatRoughnessTexture', 'clearcoatNormalTexture'], true), 'Unsupported clearcoat property.');
            str_ends_with($key, 'Texture') ? $this->texture($v) : $this->scalar($v, 0, 1);
        }
    }

    private function texture(mixed $t): void
    {
        $this->require(is_array($t), 'Invalid texture assignment.');
        $this->require(! array_diff(array_keys($t), ['index', 'texCoord', 'scale', 'strength', 'extensions', 'extras']), 'Invalid texture fields.');
        foreach (['scale', 'strength'] as $key) {
            if (isset($t[$key])) {
                $this->scalar($t[$key], 0, 1);
            }
        }
        $this->ref('textures', $t['index'] ?? null);
        $this->require(in_array($t['texCoord'] ?? 0, [0, 1], true), 'Only UV0/UV1 are supported.');
        if ($x = $t['extensions']['KHR_texture_transform'] ?? null) {
            $this->require(is_array($x) && ! array_diff(array_keys($x), ['offset', 'scale', 'rotation', 'texCoord']), 'Invalid UV transform.');
            foreach (['offset', 'scale'] as $key) {
                if (isset($x[$key])) {
                    $this->vector($x[$key], 2, -100, 100);
                }
            }
            if (isset($x['rotation'])) {
                $this->scalar($x['rotation'], -100, 100);
            }
            $this->require(in_array($x['texCoord'] ?? 0, [0, 1], true), 'Unsupported UV transform channel.');
        }
    }

    private function node(int $index, array $parent, array &$seen, int $depth): void
    {
        $n = $this->ref('nodes', $index);
        $this->require(! array_diff(array_keys($n), ['name', 'matrix', 'translation', 'rotation', 'scale', 'mesh', 'children', 'extras']), 'Unsupported node property.');
        $this->require(! isset($seen[$index]) && $depth <= 16 && ! isset($n['skin']) && empty($n['weights']) && empty($n['extensions']), 'Cyclic, shared, deep or unsupported node.');
        $seen[$index] = true;
        if (isset($n['matrix'])) {
            $this->vector($n['matrix'], 16, -100, 100);
            $this->require(! isset($n['translation']) && ! isset($n['rotation']) && ! isset($n['scale']), 'Matrix and TRS cannot be combined.');
            $local = $n['matrix'];
            $this->require(abs($local[3]) + abs($local[7]) + abs($local[11]) < .000001 && abs($local[15] - 1) < .000001, 'Non-affine matrix.');
        } else {
            $t = $n['translation'] ?? [0, 0, 0];
            $s = $n['scale'] ?? [1, 1, 1];
            $q = $n['rotation'] ?? [0, 0, 0, 1];
            $this->vector($t, 3, -100, 100);
            $this->vector($s, 3, .001, 100);
            $this->vector($q, 4, -1, 1);
            $this->require(abs(array_sum(array_map(fn ($v) => $v * $v, $q)) - 1) < .0001, 'Quaternion must be normalized.');
            [$x, $y, $z, $w] = $q;
            $local = [(1 - 2 * ($y * $y + $z * $z)) * $s[0], 2 * ($x * $y + $z * $w) * $s[0], 2 * ($x * $z - $y * $w) * $s[0], 0, 2 * ($x * $y - $z * $w) * $s[1], (1 - 2 * ($x * $x + $z * $z)) * $s[1], 2 * ($y * $z + $x * $w) * $s[1], 0, 2 * ($x * $z + $y * $w) * $s[2], 2 * ($y * $z - $x * $w) * $s[2], (1 - 2 * ($x * $x + $y * $y)) * $s[2], 0, ...$t, 1];
        }
        $matrix = array_fill(0, 16, 0);
        for ($col = 0; $col < 4; $col++) {
            for ($row = 0; $row < 4; $row++) {
                for ($k = 0; $k < 4; $k++) {
                    $matrix[$col * 4 + $row] += $parent[$k * 4 + $row] * $local[$col * 4 + $k];
                }
            }
        }
        if (isset($n['mesh'])) {
            foreach ($this->ref('meshes', $n['mesh'])['primitives'] as $primitive) {
                $a = $this->ref('accessors', $primitive['attributes']['POSITION']);
                $this->instances += $a['count'];
                $this->require($this->instances <= min(100000, config('viewer.max_vertices')), 'Instanced vertex budget exceeded.');
                $this->instanceTriangles += (isset($primitive['indices']) ? $this->ref('accessors', $primitive['indices'])['count'] : $a['count']) / 3;
                $this->instanceDrawCalls++;
                $this->require($this->instanceTriangles <= min(100000, config('viewer.max_triangles')) && $this->instanceDrawCalls <= min(64, config('viewer.max_primitives')), 'Instantiated triangle/draw-call budget exceeded.');
                for ($i = 0; $i < $a['count']; $i++) {
                    [$x, $y, $z] = $this->value($a, $i);
                    foreach ([0, 1, 2] as $axis) {
                        $v = $matrix[$axis] * $x + $matrix[4 + $axis] * $y + $matrix[8 + $axis] * $z + $matrix[12 + $axis];
                        $this->require(is_finite($v) && abs($v) <= 100, 'Extreme world coordinate.');
                        $this->bounds[0][$axis] = min($this->bounds[0][$axis], $v);
                        $this->bounds[1][$axis] = max($this->bounds[1][$axis], $v);
                    }
                }
            }
        }
        $this->require(is_array($n['children'] ?? []) && array_is_list($n['children'] ?? []) && count($n['children'] ?? []) <= 128, 'Invalid child list.');
        foreach ($n['children'] ?? [] as $child) {
            $this->require(is_int($child), 'Invalid child reference.');
            $this->node($child, $matrix, $seen, $depth + 1);
        }
    }

    private function extensions(array $v): void
    {
        foreach ($v as $key => $value) {
            $this->require($key !== 'uri', 'External and data URI resources are forbidden; embed bufferViews.');
            if ($key === 'extensions') {
                $this->require(is_array($value) && ! array_diff(array_keys($value), config('viewer.extensions')) && ! array_diff(array_keys($value), $this->json['extensionsUsed'] ?? []), 'Unsupported or undeclared extension.');
            }
            if (is_array($value)) {
                $this->extensions($value);
            }
        }
    }

    private function strip(array &$v): void
    {
        unset($v['extras']);
        foreach ($v as $key => &$value) {
            if ($key === 'name' || $key === 'generator' || $key === 'copyright') {
                $value = mb_substr(is_string($value) ? $value : '', 0, 120);
            } elseif (is_array($value)) {
                $this->strip($value);
            }
        }
    }

    private function scalar(mixed $v, float $min, float $max): void
    {
        $this->require((is_int($v) || is_float($v)) && is_finite((float) $v) && $v >= $min && $v <= $max, 'Invalid bounded material/transform value.');
    }

    private function vector(mixed $v, int $n, float $min, float $max): void
    {
        $this->require(is_array($v) && array_is_list($v) && count($v) === $n, 'Invalid vector.');
        foreach ($v as $x) {
            $this->scalar($x, $min, $max);
        }
    }

    private function require(bool $condition, string $message): void
    {
        if (isset($this->started) && ((hrtime(true) - $this->started) / 1e9 > min(8, config('viewer.max_seconds')) || memory_get_usage(true) - $this->memory > min(134217728, config('viewer.memory_budget')))) {
            throw ValidationException::withMessages(['model_upload' => 'Model validation exceeded its CPU/memory budget.']);
        }
        if (! $condition) {
            throw ValidationException::withMessages(['model_upload' => $message]);
        }
    }
}
