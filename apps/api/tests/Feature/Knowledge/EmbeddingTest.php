<?php

use App\Exceptions\DimensionMismatchException;
use App\Exceptions\EmbeddingException;
use App\Services\Embedding\EmbeddingClient;
use App\Services\Embedding\EmbeddingClientFactory;
use App\Services\Embedding\OllamaEmbeddingClient;
use App\Services\Embedding\OpenAiEmbeddingClient;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\EmbeddingsContract;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Responses\Embeddings\CreateResponse;
use Psr\Http\Client\ClientExceptionInterface;

// ── Factory resolution ────────────────────────────────────────────────────────

it('factory resolves OpenAiEmbeddingClient by default', function () {
    expect(app(EmbeddingClient::class))->toBeInstanceOf(OpenAiEmbeddingClient::class);
})->skip(fn () => env('AI_PROVIDER') === 'ollama');

it('factory resolves OllamaEmbeddingClient when AI_PROVIDER=ollama', function () {
    $client = EmbeddingClientFactory::resolve(app());

    expect($client)->toBeInstanceOf(OllamaEmbeddingClient::class);
})->skip(fn () => env('AI_PROVIDER') !== 'ollama');

// ── OpenAI client ─────────────────────────────────────────────────────────────

it('embed() returns a 1536-dim float array', function () {
    $client = makeOpenAiClient(fakeResponse(1));

    expect($client->embed('hello world'))->toBeArray()->toHaveCount(1536);
});

it('embedBatch() splits 250 texts into three OpenAI calls of 100, 100, 50', function () {
    $openaiClient = Mockery::mock(ClientContract::class);
    $resource = Mockery::mock(EmbeddingsContract::class);

    $openaiClient->shouldReceive('embeddings')->times(3)->andReturn($resource);
    $resource->shouldReceive('create')
        ->with(Mockery::on(fn ($a) => count($a['input']) === 100))
        ->twice()
        ->andReturn(fakeResponse(100));
    $resource->shouldReceive('create')
        ->with(Mockery::on(fn ($a) => count($a['input']) === 50))
        ->once()
        ->andReturn(fakeResponse(50));

    $result = (new OpenAiEmbeddingClient($openaiClient))->embedBatch(array_fill(0, 250, 'text'));

    expect($result)->toHaveCount(250);
});

it('retries up to 3 times on TransporterException then succeeds', function () {
    $openaiClient = Mockery::mock(ClientContract::class);
    $resource = Mockery::mock(EmbeddingsContract::class);

    $openaiClient->shouldReceive('embeddings')->times(3)->andReturn($resource);

    $calls = 0;
    $resource->shouldReceive('create')->times(3)->andReturnUsing(function () use (&$calls) {
        $calls++;
        if ($calls < 3) {
            $psrEx = new class('connection reset') extends Exception implements ClientExceptionInterface {};
            throw new TransporterException($psrEx);
        }

        return fakeResponse(1);
    });

    $client = new class($openaiClient) extends OpenAiEmbeddingClient
    {
        protected function retryDelay(int $attempt): void {}
    };

    expect($client->embed('test'))->toHaveCount(1536);
});

it('throws EmbeddingException after exhausting all retries', function () {
    $openaiClient = Mockery::mock(ClientContract::class);
    $resource = Mockery::mock(EmbeddingsContract::class);

    $openaiClient->shouldReceive('embeddings')->times(3)->andReturn($resource);
    $resource->shouldReceive('create')
        ->times(3)
        ->andThrow(new TransporterException(
            new class('timeout') extends Exception implements ClientExceptionInterface {},
        ));

    $client = new class($openaiClient) extends OpenAiEmbeddingClient
    {
        protected function retryDelay(int $attempt): void {}
    };

    expect(fn () => $client->embed('test'))->toThrow(EmbeddingException::class);
});

it('embedBatch returns empty array for empty input without calling the API', function () {
    $openaiClient = Mockery::mock(ClientContract::class);
    $openaiClient->shouldNotReceive('embeddings');

    expect((new OpenAiEmbeddingClient($openaiClient))->embedBatch([]))->toBe([]);
});

it('model() returns text-embedding-3-small', function () {
    expect(makeOpenAiClient(fakeResponse(0))->model())->toBe('text-embedding-3-small');
});

it('dimension() returns 1536', function () {
    expect(makeOpenAiClient(fakeResponse(0))->dimension())->toBe(1536);
});

// ── Ollama client ─────────────────────────────────────────────────────────────

it('OllamaEmbeddingClient::embed() throws DimensionMismatchException', function () {
    expect(fn () => (new OllamaEmbeddingClient('http://localhost:11434'))->embed('test'))
        ->toThrow(DimensionMismatchException::class);
});

it('OllamaEmbeddingClient::embedBatch() throws DimensionMismatchException', function () {
    expect(fn () => (new OllamaEmbeddingClient('http://localhost:11434'))->embedBatch(['a']))
        ->toThrow(DimensionMismatchException::class);
});

it('OllamaEmbeddingClient::dimension() returns 768', function () {
    expect((new OllamaEmbeddingClient('http://localhost:11434'))->dimension())->toBe(768);
});

it('OllamaEmbeddingClient::model() returns nomic-embed-text', function () {
    expect((new OllamaEmbeddingClient('http://localhost:11434'))->model())->toBe('nomic-embed-text');
});

// ── Container binding ─────────────────────────────────────────────────────────

it('EmbeddingClient resolves from the service container', function () {
    expect(app(EmbeddingClient::class))->toBeInstanceOf(EmbeddingClient::class);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function fakeResponse(int $count): CreateResponse
{
    $data = $count > 0
        ? array_map(
            fn (int $i) => ['object' => 'embedding', 'index' => $i, 'embedding' => array_fill(0, 1536, 0.1)],
            range(0, $count - 1),
        )
        : [];

    return CreateResponse::fake(['data' => $data]);
}

function makeOpenAiClient(CreateResponse $response): OpenAiEmbeddingClient
{
    $openaiClient = Mockery::mock(ClientContract::class);
    $resource = Mockery::mock(EmbeddingsContract::class);

    $openaiClient->allows('embeddings')->andReturn($resource);
    $resource->allows('create')->andReturn($response);

    return new OpenAiEmbeddingClient($openaiClient);
}
