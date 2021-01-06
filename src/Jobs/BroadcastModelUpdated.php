<?php

namespace Tonysm\TurboLaravel\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tonysm\TurboLaravel\Models\Broadcasts;

class BroadcastModelUpdated implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public Model $model;

    /**
     * BroadcastModelUpdated constructor.
     *
     * @param Model|Broadcasts $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function handle()
    {
        $this->model->hotwireBroadcastUsing()->update($this->model);
    }
}
