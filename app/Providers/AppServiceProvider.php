<?php

namespace App\Providers;

use App\Documentation\ApiOperationTransformer;
use App\Documentation\ContentParametersExtractor;
use App\Http\Requests\Admin\ContentRequest;
use App\Policies\ContentPolicy;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        FormRequestParametersExtractor::ignoreInstanceOf(ContentRequest::class);
        Scramble::configure()
            ->withParametersExtractors(fn (ParametersExtractors $extractors) => $extractors->append(ContentParametersExtractor::class))
            ->withOperationTransformers(ApiOperationTransformer::class)
            ->withDocumentTransformers(fn (OpenApi $document) => $document->secure(SecurityScheme::http('bearer', 'JWT')->as('bearerAuth')));

        Gate::define('manage-content', [ContentPolicy::class, 'manage']);
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('refresh', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
