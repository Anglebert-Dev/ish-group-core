@include('errors.layout', [
    'code' => 403,
    'title' => __('body.access_denied'),
    'message' => $exception->getMessage() ?: __('body.no_permission'),
    'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
])
