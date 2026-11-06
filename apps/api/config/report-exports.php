<?php

return [
    'retention_days' => max(1, (int) env('REPORT_EXPORT_RETENTION_DAYS', 7)),
];
