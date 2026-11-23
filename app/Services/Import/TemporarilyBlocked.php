<?php

namespace App\Services\Import;

use RuntimeException;

/** Gazduirea magazinului vechi a raspuns cu pagina anti-bot: se reia mai tarziu, fara a marca imaginea ca esuata. */
class TemporarilyBlocked extends RuntimeException {}
