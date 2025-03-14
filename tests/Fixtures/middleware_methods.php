public function handle($request, \Closure $next)
{
    $user = auth()->user();
    $propertyId = $request->route('property');
    
    if (!$propertyId) {
        return $next($request);
    }
    
    // Si el usuario puede ver todas las propiedades, permitir acceso
    if ($user->can('view-all-properties')) {
        return $next($request);
    }
    
    // Verificar si el usuario tiene acceso a la propiedad específica
    $hasAccess = $user->properties->contains($propertyId);
    
    if (!$hasAccess) {
        abort(403, 'No tienes permiso para acceder a esta propiedad.');
    }
    
    return $next($request);
}
