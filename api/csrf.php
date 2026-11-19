<?php
require __DIR__ . '/../includes/bootstrap.php';

json_response(['token' => csrf_token()]);