<?php

namespace Tests\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmptyJob
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
}
