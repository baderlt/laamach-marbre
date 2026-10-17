# M1 deployment boundary

`laamach-marbre-next.service.example` records the independent service identity and proposed release layout only. No service, Nginx virtual host, PHP-FPM pool, domain, TLS certificate or R2 bucket has been deployed.

The Node path, service account and directories must be verified on the actual host in M9. The backend requires a separate `laamach-marbre` PHP-FPM pool. M1 stays noindex; production indexing policy is a later milestone.
