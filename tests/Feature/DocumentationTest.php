<?php

namespace Tests\Feature;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_covers_every_api_route_and_has_valid_internal_references(): void
    {
        $result = app(Generator::class)->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));
        $this->assertCount(0, $result->diagnostics);
        $document = json_decode(json_encode($result->spec(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringStartsWith('3.1.', $document['openapi']);
        $operationIds = [];
        $operations = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $path = '/'.substr($route->uri(), 4);
            $attributes = (new \ReflectionMethod($route->getControllerClass(), $route->getActionMethod()))
                ->getAttributes(Endpoint::class);
            $this->assertCount(1, $attributes);
            $explicitOperationId = $attributes[0]->getArguments()['operationId'] ?? null;
            $this->assertNotEmpty($explicitOperationId, "OperationId explícito ausente em {$route->uri()}");
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $explicitOperationId);
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->assertArrayHasKey($path, $document['paths']);
                $this->assertArrayHasKey(strtolower($method), $document['paths'][$path]);
                $operation = $document['paths'][$path][strtolower($method)];
                $this->assertSame($explicitOperationId, $operation['operationId']);
                $this->assertNotEmpty($operation['description']);
                $this->assertNotContains($operation['operationId'], $operationIds);
                $operationIds[] = $operation['operationId'];
                $this->assertArrayHasKey('500', $operation['responses']);
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
        $this->assertSame([['bearerAuth' => []]], $document['security']);
        $this->assertSame([], $document['paths']['/auth/login']['post']['security']);
        $this->assertSame([], $document['paths']['/auth/register']['post']['security']);
        $this->assertArrayNotHasKey('security', $document['paths']['/auth/refresh']['post']);
        $validation = $document['paths']['/auth/login']['post']['responses']['422']['content']['application/json']['schema'];
        $this->assertSame(['error', 'message', 'data', 'errors'], $validation['required']);
        $this->assertSame('object', $document['components']['schemas']['ContentResource']['type']);
        $this->assertArrayNotHasKey('created_by', $document['components']['schemas']['CreateLearningPath']['properties']);
        foreach (['LearningPath', 'Stage', 'Lesson', 'Exercise'] as $model) {
            $this->assertNotEmpty($document['components']['schemas']['Create'.$model]['required']);
            $this->assertEmpty($document['components']['schemas']['Update'.$model]['required'] ?? []);
        }
        $this->assertNotContains('submission', $document['components']['schemas']['ProgressResultResource']['required']);
        $this->assertSame('integer', $document['components']['schemas']['DashboardResource']['properties']['started_paths_count']['type']);
    }
}
