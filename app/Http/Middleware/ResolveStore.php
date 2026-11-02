<?php

namespace App\Http\Middleware;

/**
 * ResolveStore middleware.
 * Extends IdentifyTenant for unified host/header extraction, container binding,
 * and storefront access control.
 */
class ResolveStore extends IdentifyTenant
{
    // Inherits handle() and extractSubdomain() from IdentifyTenant
}