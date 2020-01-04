<?php

namespace App\Http\Middleware;

use App\Models\AppKey;
use App\Models\RequestLog;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Closure;

class ApiAuthorization
{

    const AUTH_HEADER = 'X-Authorization';

    /** @var RequestLog */
    private $requestLog;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header(self::AUTH_HEADER);
        $apiKey = AppKey::getByKey($header);

        $this->requestLog = new RequestLog([
            'origin' => 'api',
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'headers' => json_encode($request->headers->all()),
            'params' => json_encode($request->request->all()),
            'ip' => $request->getClientIp(),
        ]);
        $this->requestLog->save();

        if ($apiKey instanceof AppKey) {
            $this->requestLog->app_id = $apiKey->app_id;
            $this->requestLog->save();
            $request->request->add(['request_log' => $this->requestLog]);
            return $next($request);
        }

        return response([
            'errors' => [[
                'message' => 'Unauthorized'
            ]]
        ], 401);

    }

    /**
     * @param Request $request
     * @param Response|JsonResponse $response
     */
    public function terminate(Request $request, $response)
    {
        /** @var RequestLog $requestLog */
        $requestLog = RequestLog::find($request->request_log->id);
        $requestLog->status_code = $response->getStatusCode();
        $requestLog->response = json_encode($response->getOriginalContent());
        $requestLog->exec_time = microtime(true) - LARAVEL_START;
        $requestLog->save();
    }
}
