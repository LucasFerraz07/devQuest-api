<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    public function test_openapi_covers_every_api_route_and_has_valid_internal_references(): void
    {
        $document = json_decode(file_get_contents(base_path('docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('3.1.0', $document['openapi']);
        $operations = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $path = '/'.substr($route->uri(), 4);
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->assertArrayHasKey($path, $document['paths']);
                $this->assertArrayHasKey(strtolower($method), $document['paths'][$path]);
                $operations++;
            }
        }
        $this->assertSame(39, $operations);
        $visit = function (array $node) use (&$visit, $document): void {
            if (isset($node['$ref'])) {
                $reference = $document;
                foreach (explode('/', substr($node['$ref'], 2)) as $key) {
                    $this->assertArrayHasKey($key, $reference);
                    $reference = $reference[$key];
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $visit($child);
                }
            }
        };
        $visit($document);
    }
}
