<?php

namespace App\Documentation;

use App\Http\Resources\Admin\ContentResource;
use App\Http\Resources\Learning\DashboardResource;
use App\Http\Resources\Learning\ProgressResultResource;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionMethod;

/** Uses explicit output shapes for resources backed by arrays or multiple model types. */
class TypedResourceSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && in_array($type->name, [
            ContentResource::class, DashboardResource::class, ProgressResultResource::class,
        ], true);
    }

    public function toSchema(Type $type)
    {
        $doc = PhpDoc::parse(
            (new ReflectionMethod($type->name, 'toArray'))->getDocComment(),
            new FileNameResolver(ClassReflector::make($type->name)->getNameContext()),
        );
        $shape = PhpDocTypeHelper::toType($doc->getReturnTagValues()[0]->type);

        return $this->openApiTransformer->getOrCreateSchemaReference(
            ClassBasedReference::create('schemas', $type->name, $this->components),
            fn () => $this->openApiTransformer->transform($shape),
        );
    }
}
