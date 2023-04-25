<?php

header('HTTP/1.1 400 Bad Request', true, 400);
header('Content-Type: application/json');

echo json_encode(['error' => $error]);
