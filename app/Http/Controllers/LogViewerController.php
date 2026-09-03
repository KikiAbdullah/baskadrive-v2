<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LogViewerController extends Controller
{
    private $levels_classes = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'danger',
        'critical' => 'danger',
        'alert' => 'danger',
        'emergency' => 'danger',
        'processed' => 'info',
        'failed' => 'warning',
    ];

    private $patterns = [
        'logs' => '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}([\+-]\d{4})?\].*/',
        'current_log' => [
            '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}([\+-]\d{4})?)\](?:.*?(\w+)\.|.*?)',
            ': (.*?)( in .*?:[0-9]+)?$/i',
        ],
        'files' => '/at ([^:]+):(\d+)/',
    ];

    public function index(Request $request)
    {
        ini_set('memory_limit', '256M');
        $file = \File::get(storage_path('logs/laravel.log'));

        preg_match_all($this->getPattern('logs'), $file, $heading);
        rsort($heading[0]);
        $hasil = array_slice($heading[0], 0, 50);

        $log = [];

        foreach ($hasil as $h) {
            foreach (array_keys($this->levels_classes) as $level) {
                preg_match($this->getPattern('current_log', 0).$level.$this->getPattern('current_log', 1), $h, $current);
                if (! isset($current[4])) {
                    continue;
                }
                $error = explode(' at ', $current[4]);

                preg_match('/([a-zA-Z0-9\-_\.]+\.php):(\d+)/', ($error[1] ?? ''), $file);

                if (! empty($file)) {
                    if (! array_key_exists($file[1].$file[2], $log)) {
                        $log[$file[1].$file[2]] = [
                            'context' => $current[3] ?? '',
                            'date' => formatDate('Y-m-d H:i:s', 'd/m/y H:i:s', $current[1]),
                            'text' => $error[0] ?? '',
                            'file' => $file[1] ?? '',
                            'line' => $file[2] ?? '',
                            'urgent' => $this->levels_classes[$level],
                        ];
                    }
                }
            }
        }

        $data['logs'] = $log;
        $data['icons'] = [
            'danger' => '<i class="ri-error-warning-line text-danger"></i>',
            'info' => '<i class="ri-error-warning-line text-info"></i>',
        ];
        $data['title'] = 'Log Viewer';
        $data['subtitle'] = 'List of Log Viewer';

        return view('debug.log_viewer.index', $data);
    }

    /**
     * @return array
     */
    public function all()
    {
        return array_keys($this->patterns);
    }

    /**
     * @return string pattern
     */
    public function getPattern($pattern, $position = null)
    {
        if ($position !== null) {
            return $this->patterns[$pattern][$position];
        }

        return $this->patterns[$pattern];
    }
}
