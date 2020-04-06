# Migration

## General

Update your version of this library using Composer and its `composer update` or `composer require` commands [[?]](https://github.com/delight-im/Knowledge/blob/master/Composer%20(PHP).md#how-do-i-update-libraries-or-modules-within-my-application).

## From `v2.x.x` to `v3.x.x`

 * The default domain scope for new `Cookie` instances is still the current host, but it does not include subdomains anymore, unless explicitly specified otherwise via the `Cookie#setDomain` method.
 * For the domain scope, `www` subdomains are not automatically widened to the bare domain anymore. If you want to include the bare domain and all subdomains in addition to the `www` subdomain, you must now explicitly specifiy the bare domain instead of the `www` subdomain as the scope.
 * When managing sessions via the methods `Session#start` or `Session#regenerate`, the session configuration is now correctly respected with regard to whether subdomains should be included in the domain scope or not. The scope is not automatically widened to include subdomains anymore.
 * The second parameter of the `Cookie#setDomain` method, which was named `$keepWww`, has been removed.
 * When creating `Cookie` instances from a string using the `Cookie#parse` method, the source’s decision of whether to include subdomains in the domain scope or not is now correctly respected. The scope is not automatically widened to include subdomains anymore.

## From `v1.x.x` to `v2.x.x`

 * The license has been changed from the [Apache License 2.0](http://www.apache.org/licenses/LICENSE-2.0) to the [MIT License](https://opensource.org/licenses/MIT).
