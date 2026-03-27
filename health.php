<?php
// 健康检查（可选，用于监控）
http_response_code(200);
echo json_encode(['status'=>'ok','time'=>date('Y-m-d H:i:s')]);
