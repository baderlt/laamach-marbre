<?php

namespace Tests\Unit;

use App\Media\GlbValidator;
use Illuminate\Validation\ValidationException;
use Tests\Support\ModelFixtures;
use Tests\TestCase;

class GlbValidatorTest extends TestCase
{
    public function test_static_self_contained_geometry_and_compatible_pbr_are_preserved(): void
    {
        $bytes = ModelFixtures::glb(true);
        $result = app(GlbValidator::class)->validate($bytes);
        $this->assertSame(8, $result['metadata']['vertices']);
        $this->assertSame(12, $result['metadata']['triangles']);
        $this->assertSame(1, $result['metadata']['textures']);
        $this->assertSame(4, $result['metadata']['texture_pixels']);
        $this->assertEquals(['width' => 1, 'height' => 1, 'thickness' => 1], $result['metadata']['scene_bounds_m']);
        $this->assertStringNotContainsString('synthetic-private-metadata', $result['bytes']);
        $again = app(GlbValidator::class)->validate($result['bytes']);
        $this->assertSame($result['bytes'], $again['bytes']);
    }

    public function test_binary_headers_chunks_json_and_lengths_are_checked(): void
    {
        $good = ModelFixtures::glb();
        foreach (['bad!'.substr($good, 4), substr_replace($good, pack('V', 1), 4, 4), substr_replace($good, pack('V', strlen($good) + 4), 8, 4), substr_replace($good, pack('V', 3), 12, 4), substr_replace($good, '!', 20, 1), substr($good, 0, -1)] as $bytes) {
            try {
                app(GlbValidator::class)->validate($bytes);
                $this->fail('Malformed binary accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_external_references_invalid_ranges_materials_extensions_and_nonfinite_data_are_rejected(): void
    {
        $mutations = [
            fn ($j) => array_replace($j, ['buffers' => []]),
            function ($j) {
                $j['buffers'][0]['uri'] = 'https://example.invalid/a.bin';

                return $j;
            },
            function ($j) {
                $j['images'] = [['uri' => 'data:image/png;base64,AAAA']];

                return $j;
            },
            function ($j) {
                $j['bufferViews'][0]['byteLength'] = 999999;

                return $j;
            },
            function ($j) {
                $j['accessors'][0]['byteOffset'] = 1;

                return $j;
            },
            function ($j) {
                $j['accessors'][0]['count'] = 900000;

                return $j;
            },
            function ($j) {
                $j['accessors'][0]['sparse'] = ['count' => 1];

                return $j;
            },
            function ($j) {
                $j['meshes'][0]['primitives'][0]['material'] = 99;

                return $j;
            },
            function ($j) {
                $j['extensionsUsed'] = $j['extensionsRequired'] = ['KHR_draco_mesh_compression'];

                return $j;
            },
            function ($j) {
                $j['nodes'][0]['children'] = [0];

                return $j;
            },
            function ($j) {
                $j['nodes'][0]['camera'] = 0;

                return $j;
            },
            function ($j) {
                $j['materials'][0]['pbrMetallicRoughness']['roughnessFactor'] = -1;

                return $j;
            },
            function ($j) {
                $j['extensionsUsed'] = 'not-an-array';

                return $j;
            },
        ];
        foreach ($mutations as $change) {
            [$j,$bin] = ModelFixtures::document();
            try {
                app(GlbValidator::class)->validate(ModelFixtures::pack($change($j), $bin));
                $this->fail('Malformed structure accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        [$j,$bin] = ModelFixtures::document();
        foreach ([substr_replace($bin, pack('g', NAN), 0, 4), substr_replace($bin, pack('v', 65535), 96, 2)] as $data) {
            try {
                app(GlbValidator::class)->validate(ModelFixtures::pack($j, $data));
                $this->fail('Invalid vertex/index data accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_file_geometry_texture_and_processing_budgets_are_bounded(): void
    {
        $bytes = ModelFixtures::glb(true);
        foreach (['max_bytes' => 32, 'max_vertices' => 7, 'max_triangles' => 11, 'max_texture_dimension' => 1, 'max_texture_pixels' => 3, 'max_texture_count' => 0, 'max_decoded_components' => 4, 'max_seconds' => 0] as $key => $limit) {
            $original = config('viewer.'.$key);
            config(['viewer.'.$key => $limit]);
            try {
                app(GlbValidator::class)->validate($bytes);
                $this->fail('Budget was ignored: '.$key);
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            } finally {
                config(['viewer.'.$key => $original]);
            }
        }
        $this->expectException(ValidationException::class);
        app(GlbValidator::class)->validate(str_repeat('x', 8388609));
    }
}
