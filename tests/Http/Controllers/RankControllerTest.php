<?php

namespace Rubix\Server\Tests\Http\Controllers;

use Rubix\Server\CommandBus;
use Rubix\Server\Commands\Rank;
use Rubix\Server\Serializers\JSON;
use Rubix\Server\Http\Controllers\RankController;
use Rubix\Server\Http\Controllers\Controller;
use Rubix\Server\Responses\RankResponse;
use React\Http\Io\ServerRequest;
use Psr\Http\Message\ResponseInterface as Response;
use PHPUnit\Framework\TestCase;

class RankControllerTest extends TestCase
{
    protected $controller;

    public function setUp()
    {
        $commandBus = $this->createMock(CommandBus::class);

        $commandBus->method('dispatch')
            ->willReturn(new RankResponse([]));

        $this->controller = new RankController($commandBus, new JSON());
    }

    public function test_build_controller()
    {
        $this->assertInstanceOf(RankController::class, $this->controller);
        $this->assertInstanceOf(Controller::class, $this->controller);
    }

    public function test_handle_request()
    {
        $request = new ServerRequest('POST', '/example', [], json_encode([
            'name' => Rank::class,
            'data' => [
                'samples' => [
                    ['The first step is to establish that something is possible, then probability will occur.'],
                ],
            ],
        ]) ?: null);

        $response = $this->controller->handle($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
