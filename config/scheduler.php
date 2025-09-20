<?php

return array (
  'scheduler' => 
  array (
    'type' => 'distributed',
    'local' => 
    array (
      'pool_size' => 64,
      'max_exec_time' => 30,
    ),
    'distributed' => 
    array (
      'cluster_nodes' => 3,
      'node_address' => '127.0.0.1',
      'port' => 8000,
      'discovery' => 
      array (
        'type' => 'static',
        'nodes' => 
        array (
          0 => 
          array (
            'id' => 'node_0',
            'address' => '127.0.0.1',
            'port' => 8000,
          ),
          1 => 
          array (
            'id' => 'node_1',
            'address' => '127.0.0.1',
            'port' => 8001,
          ),
          2 => 
          array (
            'id' => 'node_2',
            'address' => '127.0.0.1',
            'port' => 8002,
          ),
        ),
      ),
    ),
  ),
);
