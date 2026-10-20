<?php
namespace App\Http\Middleware;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
/** Audits successful visits to restricted research data pages; no tokens, IP or query strings. */
class AuditSensitiveRead {
 public function handle(Request $request, Closure $next):Response {
  $response=$next($request);
  if($request->isMethod('GET') && $response->getStatusCode()>=200 && $response->getStatusCode()<400 && auth()->check()){
   Audit::log('restricted.read','routes',null,['route'=>$request->route()?->getName()]);
  }
  return $response;
 }
}
