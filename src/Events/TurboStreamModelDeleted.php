<?php

namespace Tonysm\TurboLaravel\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Tonysm\TurboLaravel\Models\Broadcasts;

class TurboStreamModelDeleted implements ShouldBroadcastNow
{
    public Model $model;
    public string $action;

    /**
     * TurboStreamModelDeleted constructor.
     *
     * @param Model|Broadcasts $model
     * @param string $action
     */
    public function __construct(Model $model, string $action = "remove")
    {
        $this->model = $model;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        return $this->model->hotwireBroadcastsOn();
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->render(),
        ];
    }

    public function render()
    {
        return View::make('turbo-laravel::model-removed', [
            'target' => $this->model->hotwireTargetDomId(),
            'action' => $this->action,
        ])->render();
    }
}
