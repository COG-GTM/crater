<?php

return [

    /*
     * Proxies trusted by Crater\Http\Middleware\TrustProxies. Use "*" to trust
     * the proxy that connects directly to the server (e.g. a load balancer),
     * or a comma-separated list of IP addresses / CIDR ranges.
     */
    'proxies' => env('TRUSTED_PROXIES', '*'),

];
