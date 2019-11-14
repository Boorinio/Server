<?php

namespace Rubix\Server;

use Rubix\ML\Learner;
use Rubix\ML\Ranking;
use Rubix\ML\Estimator;
use Rubix\ML\Probabilistic;
use Rubix\Server\Commands\Predict;
use Rubix\Server\Commands\PredictSample;
use Rubix\Server\Commands\Proba;
use Rubix\Server\Commands\ProbaSample;
use Rubix\Server\Commands\Rank;
use Rubix\Server\Commands\RankSample;
use Rubix\Server\Commands\QueryModel;
use Rubix\Server\Commands\ServerStatus;
use Rubix\Server\Handlers\PredictHandler;
use Rubix\Server\Handlers\PredictSampleHandler;
use Rubix\Server\Handlers\ProbaHandler;
use Rubix\Server\Handlers\ProbaSampleHandler;
use Rubix\Server\Handlers\RankHandler;
use Rubix\Server\Handlers\RankSampleHandler;
use Rubix\Server\Handlers\QueryModelHandler;
use Rubix\Server\Handlers\ServerStatusHandler;
use Rubix\Server\Http\Middleware\Middleware;
use Rubix\Server\Http\Controllers\PredictionsController;
use Rubix\Server\Http\Controllers\SamplePredictionController;
use Rubix\Server\Http\Controllers\ProbabilitiesController;
use Rubix\Server\Http\Controllers\SampleProbabilitiesController;
use Rubix\Server\Http\Controllers\QueryModelController;
use Rubix\Server\Http\Controllers\ScoresController;
use Rubix\Server\Http\Controllers\SampleScoreController;
use Rubix\Server\Http\Controllers\ServerStatusController;
use Rubix\Server\Traits\LoggerAware;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use FastRoute\DataGenerator\GroupCountBased as GroupCountBasedDataGenerator;
use FastRoute\Dispatcher\GroupCountBased as GroupCountBasedDispatcher;
use FastRoute\Dispatcher;
use React\Http\Server as HTTPServer;
use React\Socket\Server as Socket;
use React\Socket\SecureServer as SecureSocket;
use React\EventLoop\Factory as Loop;
use React\Http\Response as ReactResponse;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use InvalidArgumentException;

/**
 * HTTP Server
 *
 * A standalone Json over HTTP and secure HTTP server exposing a REST
 * (Representational State Transfer) API.
 *
 * @category    Machine Learning
 * @package     Rubix/Server
 * @author      Andrew DalPino
 */
class RESTServer implements Server, Verbose
{
    use LoggerAware;

    public const MODEL_PREFIX = '/model';
    public const SERVER_PREFIX = '/server';

    public const PREDICT_ENDPOINT = '/predictions';
    public const PREDICT_SAMPLE_ENDPOINT = '/sample_prediction';
    public const PROBA_ENDPOINT = '/probabilities';
    public const PROBA_SAMPLE_ENDPOINT = '/sample_probabilities';
    public const RANK_ENDPOINT = '/scores';
    public const RANK_SAMPLE_ENDPOINT = '/sample_score';
    public const SERVER_STATUS_ENDPOINT = '/status';

    protected const NOT_FOUND = 404;
    protected const METHOD_NOT_ALLOWED = 405;
    protected const INTERNAL_SERVER_ERROR = 500;

    /**
     * The host address to bind the server to.
     *
     * @var string
     */
    protected $host;

    /**
     * The network port to run the HTTP services on.
     *
     * @var int
     */
    protected $port;

    /**
     * The path to the certificate used to authenticate and encrypt the
     * secure (HTTPS) communication channel.
     *
     * @var string|null
     */
    protected $cert;

    /**
     * The HTTP middleware stack.
     *
     * @var \Rubix\Server\Http\Middleware\Middleware[]
     */
    protected $middleware;

    /**
     * The controller dispatcher i.e the router.
     *
     * @var \FastRoute\Dispatcher
     */
    protected $router;

    /**
     * The timestamp from when the server went up.
     *
     * @var int|null
     */
    protected $start;

    /**
     * The number of requests that have been handled during this
     * run of the server.
     *
     * @var int
     */
    protected $requests = 0;

    /**
     * @param string $host
     * @param int $port
     * @param string|null $cert
     * @param array $middleware
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 8080,
        ?string $cert = null,
        array $middleware = []
    ) {
        if (empty($host)) {
            throw new InvalidArgumentException('Host cannot be empty.');
        }

        if ($port < 0) {
            throw new InvalidArgumentException('Port number must be'
                . " a positive integer, $port given.");
        }

        if (isset($cert) and empty($cert)) {
            throw new InvalidArgumentException('Certificate cannot be'
                . ' empty.');
        }

        foreach ($middleware as $mw) {
            if (!$mw instanceof Middleware) {
                throw new InvalidArgumentException('Class must implement'
                . ' middleware interface, ' . get_class($mw) . ' given.');
            }
        }

        $this->host = $host;
        $this->port = $port;
        $this->cert = $cert;
        $this->middleware = array_values($middleware);
    }

    /**
     * Return the number of requests that have been received.
     *
     * @var int
     */
    public function requests() : int
    {
        return $this->requests;
    }

    /**
     * Return the uptime of the server in seconds.
     *
     * @return int
     */
    public function uptime() : int
    {
        return $this->start ? (time() - $this->start) ?: 1 : 0;
    }

    /**
     * Serve a model.
     *
     * @param \Rubix\ML\Estimator $estimator
     * @throws \InvalidArgumentException
     */
    public function serve(Estimator $estimator) : void
    {
        if ($estimator instanceof Learner) {
            if (!$estimator->trained()) {
                throw new InvalidArgumentException('Cannot serve'
                    . ' an untrained learner.');
            }
        }

        $bus = $this->bootCommandBus($estimator);

        $this->router = $this->bootRouter($estimator, $bus);

        $loop = Loop::create();

        $socket = new Socket("$this->host:$this->port", $loop);

        if ($this->cert) {
            $socket = new SecureSocket($socket, $loop, [
                'local_cert' => $this->cert,
            ]);
        }

        $stack = $this->middleware;
        $stack[] = [$this, 'handle'];

        $server = new HTTPServer($stack);

        $server->listen($socket);

        $this->start = time();
        $this->requests = 0;

        if ($this->logger) {
            $this->logger->info('HTTP REST Server running at'
                . " $this->host on port $this->port");
        }

        $loop->run();
    }

    /**
     * Boot up and return the command bus.
     *
     * @param \Rubix\ML\Estimator $estimator
     * @return \Rubix\Server\CommandBus
     */
    protected function bootCommandBus(Estimator $estimator) : CommandBus
    {
        $commands = [];

        switch (true) {
            case $estimator instanceof Estimator:
                $commands[QueryModel::class] = new QueryModelHandler($estimator);
                $commands[Predict::class] = new PredictHandler($estimator);

                // no break
            case $estimator instanceof Learner:
                $commands[PredictSample::class] = new PredictSampleHandler($estimator);

                // no break
            case $estimator instanceof Probabilistic:
                $commands[Proba::class] = new ProbaHandler($estimator);
                $commands[ProbaSample::class] = new ProbaSampleHandler($estimator);

                // no break
            case $estimator instanceof Ranking:
                $commands[Rank::class] = new RankHandler($estimator);
                $commands[RankSample::class] = new RankSampleHandler($estimator);
        }

        if ($this instanceof Verbose) {
            $commands[ServerStatus::class] = new ServerStatusHandler($this);
        }

        return new CommandBus($commands);
    }

    /**
     * Boot up and return the router.
     *
     * @param \Rubix\ML\Estimator $estimator
     * @param \Rubix\Server\CommandBus $bus
     * @return \FastRoute\Dispatcher
     */
    protected function bootRouter(Estimator $estimator, CommandBus $bus) : Dispatcher
    {
        $collector = new RouteCollector(new Std(), new GroupCountBasedDataGenerator());

        $collector->get(self::MODEL_PREFIX, new QueryModelController($bus));

        $collector->addGroup(
            self::MODEL_PREFIX,
            function ($group) use ($estimator, $bus) {
                $group->post(
                    self::PREDICT_ENDPOINT,
                    new PredictionsController($bus)
                );

                switch (true) {
                    case $estimator instanceof Learner:
                        $group->post(
                            self::PREDICT_SAMPLE_ENDPOINT,
                            new SamplePredictionController($bus)
                        );
                
                        // no break
                    case $estimator instanceof Probabilistic:
                        $group->post(
                            self::PROBA_ENDPOINT,
                            new ProbabilitiesController($bus)
                        );

                        $group->post(
                            self::PROBA_SAMPLE_ENDPOINT,
                            new SampleProbabilitiesController($bus)
                        );

                        // no break
                    case $estimator instanceof Ranking:
                        $group->post(
                            self::RANK_ENDPOINT,
                            new ScoresController($bus)
                        );

                        $group->post(
                            self::RANK_SAMPLE_ENDPOINT,
                            new SampleScoreController($bus)
                        );
                }
            }
        );

        $collector->addGroup(
            self::SERVER_PREFIX,
            function ($group) use ($bus) {
                if ($this instanceof Verbose) {
                    $group->get(
                        self::SERVER_STATUS_ENDPOINT,
                        new ServerStatusController($bus)
                    );
                }
            }
        );

        return new GroupCountBasedDispatcher($collector->getData());
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request) : Response
    {
        $method = $request->getMethod();

        $uri = $request->getUri()->getPath();

        $route = $this->router->dispatch($method, $uri);

        [$status, $controller, $params] = array_pad($route, 3, null);

        ++$this->requests;

        switch ($status) {
            case Dispatcher::FOUND:
                $response = $controller->handle($request, $params);

                break 1;

            case Dispatcher::NOT_FOUND:
                $response = new ReactResponse(self::NOT_FOUND);

                break 1;

            case Dispatcher::METHOD_NOT_ALLOWED:
                $response = new ReactResponse(self::METHOD_NOT_ALLOWED);

                break 1;

            default:
                $response = new ReactResponse(self::INTERNAL_SERVER_ERROR);
        }

        if ($this->logger) {
            $status = (string) $response->getStatusCode();

            $server = $request->getServerParams();

            $ip = $server['HTTP_CLIENT_IP'] ?? $server['REMOTE_ADDR'] ?? 'unknown';
            
            $this->logger->info("$status $method $uri from $ip");
        }

        return $response;
    }
}
