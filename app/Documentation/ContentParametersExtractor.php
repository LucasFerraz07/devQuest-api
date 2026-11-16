<?php

namespace App\Documentation;

use App\Enums\ContentType;
use App\Http\Requests\Admin\ContentRequest;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\GeneratesParametersFromRules;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;

class ContentParametersExtractor implements ParameterExtractor
{
    use GeneratesParametersFromRules;

    public function __construct(private TypeTransformer $transformer) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! isset($routeInfo->route->defaults['content_type'])
            || ! in_array($routeInfo->route->getActionMethod(), ['store', 'update'], true)) {
            return $parameterExtractionResults;
        }

        $type = ContentType::from($routeInfo->route->defaults['content_type']);
        $updating = $routeInfo->route->getActionMethod() === 'update';
        $rules = (new ContentRequest)->rulesFor($type, $updating);
        unset($rules['created_by']); // A prohibited field is not part of the input contract.
        $parameterExtractionResults[] = new ParametersExtractionResult(
            parameters: $this->makeParameters(
                rules: $rules,
                typeTransformer: $this->transformer,
                in: 'body',
            ),
            schemaName: ($updating ? 'Update' : 'Create').class_basename($type->model()),
        );

        return $parameterExtractionResults;
    }
}
