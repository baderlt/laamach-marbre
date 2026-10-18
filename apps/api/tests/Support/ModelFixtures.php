<?php

namespace Tests\Support;

/** Synthetic geometric fixtures only; never production product models. */
class ModelFixtures
{
    public static function document(bool $textured = false): array
    {
        $positions = [-.5, -.5, -.5, .5, -.5, -.5, .5, .5, -.5, -.5, .5, -.5, -.5, -.5, .5, .5, -.5, .5, .5, .5, .5, -.5, .5, .5];
        $indices = [0, 2, 1, 0, 3, 2, 4, 5, 6, 4, 6, 7, 0, 1, 5, 0, 5, 4, 2, 3, 7, 2, 7, 6, 0, 4, 7, 0, 7, 3, 1, 2, 6, 1, 6, 5];
        $bin = pack('g*', ...$positions).pack('v*', ...$indices);
        $json = ['asset' => ['version' => '2.0', 'extras' => ['private_note' => 'synthetic-private-metadata']], 'scene' => 0, 'scenes' => [['nodes' => [0]]], 'nodes' => [['mesh' => 0]], 'buffers' => [['byteLength' => strlen($bin)]], 'bufferViews' => [['buffer' => 0, 'byteOffset' => 0, 'byteLength' => 96], ['buffer' => 0, 'byteOffset' => 96, 'byteLength' => 72]], 'accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 8, 'type' => 'VEC3', 'min' => [-.5, -.5, -.5], 'max' => [.5, .5, .5]], ['bufferView' => 1, 'componentType' => 5123, 'count' => 36, 'type' => 'SCALAR']], 'materials' => [['pbrMetallicRoughness' => ['baseColorFactor' => [.65, .55, .4, 1], 'metallicFactor' => 0, 'roughnessFactor' => .8]]], 'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0], 'indices' => 1, 'material' => 0]]]]];
        if ($textured) {
            $uv = pack('g*', 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 1);
            $offset = strlen($bin);
            $bin .= $uv;
            $json['bufferViews'][] = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($uv)];
            $json['accessors'][] = ['bufferView' => 2, 'componentType' => 5126, 'count' => 8, 'type' => 'VEC2'];
            $json['meshes'][0]['primitives'][0]['attributes']['TEXCOORD_0'] = 2;
            $image = imagecreatetruecolor(2, 2);
            imagefill($image, 0, 0, imagecolorallocate($image, 170, 150, 110));
            ob_start();
            imagepng($image);
            $png = ob_get_clean();
            imagedestroy($image);
            $json['bufferViews'][] = ['buffer' => 0, 'byteOffset' => strlen($bin), 'byteLength' => strlen($png)];
            $bin .= $png;
            $bin .= str_repeat("\0", (4 - strlen($bin) % 4) % 4);
            $json['buffers'][0]['byteLength'] = strlen($bin);
            $json['images'] = [['bufferView' => 3, 'mimeType' => 'image/png']];
            $json['textures'] = [['source' => 0]];
            $json['materials'][0]['pbrMetallicRoughness']['baseColorTexture'] = ['index' => 0];
        }

        return [$json, $bin];
    }

    public static function pack(array $json, string $bin): string
    {
        $text = json_encode($json, JSON_THROW_ON_ERROR);
        $text .= str_repeat(' ', (4 - strlen($text) % 4) % 4);
        $bin .= str_repeat("\0", (4 - strlen($bin) % 4) % 4);

        return pack('V*', 0x46546C67, 2, 28 + strlen($text) + strlen($bin), strlen($text), 0x4E4F534A).$text.pack('V*', strlen($bin), 0x004E4942).$bin;
    }

    public static function glb(bool $textured = false): string
    {
        return self::pack(...self::document($textured));
    }
}
