<?php

namespace App\Services\AdminAi;

/**
 * Satu jawaban Copilot yang sedang dikirim ke browser.
 * Mengumpulkan teks + blok agar bisa disimpan sekali di akhir.
 */
final class CopilotTurn
{
    public string $reply = '';

    /** @var list<array<string, mixed>> */
    public array $blocks = [];

    public bool $started = false;

    /**
     * @param  callable(string, array<string, mixed>): bool  $emit  false = klien putus
     */
    public function __construct(private $emit) {}

    public function text(string $delta): void
    {
        if ($delta === '') {
            return;
        }
        $this->reply .= $delta;
        $this->started = true;
        $this->send('delta', ['text' => $delta]);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    public function block(array $block): void
    {
        $this->blocks[] = $block;
        $this->started = true;
        $this->send('block', ['block' => $block]);
    }

    public function status(string $label): void
    {
        $this->send('status', ['label' => $label]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(string $event, array $data): void
    {
        if (($this->emit)($event, $data) === false) {
            throw new CopilotStreamAborted('Klien menghentikan permintaan');
        }
    }
}
