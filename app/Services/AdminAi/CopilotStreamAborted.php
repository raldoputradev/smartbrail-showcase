<?php

namespace App\Services\AdminAi;

use RuntimeException;

/** Pengguna menekan Stop / berpindah percakapan / koneksi browser terputus. */
final class CopilotStreamAborted extends RuntimeException
{
}
