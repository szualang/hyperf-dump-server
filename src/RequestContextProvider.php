<?php

declare(strict_types = 1);

namespace Qiutuleng\HyperfDumpServer;

use Hyperf\HttpMessage\Uri\Uri;
use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Utils\Context;
use Hyperf\Utils\Str;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpKernel\Log\Logger;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\ContextProvider\ContextProviderInterface;

class RequestContextProvider implements ContextProviderInterface
{
    /**
     * The current request.
     *
     * @var Request|null
     */
    private ?Request $currentRequest;

    /**
     * The variable cloner.
     *
     * @var \Symfony\Component\VarDumper\Cloner\VarCloner
     */
    private VarCloner $cloner;

    /**
     * RequestContextProvider constructor.
     *
     * @param Request $currentRequest
     */
    public function __construct(Request $currentRequest = null)
    {
        $this->currentRequest = $currentRequest;
        $this->cloner = new VarCloner;
        $this->cloner->setMaxItems(0);
    }

    /**
     * Get the context.
     *
     * @return array|null
     */
    public function getContext(): ?array
    {
        if ($this->currentRequest === null) {
            return null;
        }

        return [
            'uri' => $this->getUri(),
            'method' => $this->currentRequest->getMethod(),
            'controller' => $this->getController(),
            'identifier' => \Hyperf\Utils\Coroutine::inCoroutine()
                ? spl_object_hash(Context::get(ServerRequestInterface::class))
                : Str::random(), // TODO: 非协程模式下，暂时未找到合适的唯一对象，先用随机数代替，缺陷是每次都会执行$io->section
        ];
    }

    protected function getUri(): string
    {
        $uri = $this->currentRequest->getUri();

        return Uri::composeComponents(
            $uri->getScheme(),
            $uri->getAuthority(),
            $uri->getPath(),
            $uri->getQuery(),
            $uri->getFragment()
        );
    }

    protected function getController()
    {
        /** @var Dispatched $dispatched */
        $dispatched = $this->currentRequest->getAttribute(Dispatched::class);

        if (
            is_null($dispatched)
            || is_null($handler = $dispatched->handler)
            || is_null($callback = $handler->callback)
        ) {
            return $this->cloner->cloneVar(null);
        }

        if (is_array($callback)) {
            $callback = $this->transformRouteArrayAction($callback);
        }

        return $this->cloner->cloneVar(class_basename($callback));
    }

    protected function transformRouteArrayAction(array $action): string
    {
        return "{$action[0]}@{$action[1]}";
    }
}
