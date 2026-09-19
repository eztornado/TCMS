<?php

use App\Support\Schedule\TcmsSchedule;
use Illuminate\Console\Scheduling\Schedule;

/*
| Programación de tareas del core. La definición vive compartida en
| App\Support\Schedule\TcmsSchedule para que también la ejecute la app
| nativa (catch-up al arrancar). Los módulos de proyecto pueden añadir
| las suyas desde su ServiceProvider con `Schedule::command(...)`.
*/

TcmsSchedule::register(app(Schedule::class));
