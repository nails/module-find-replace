<?php

namespace Tests\FindReplace\Factory;

use Nails\FindReplace\Factory\Action;
use PHPUnit\Framework\TestCase;

class ActionTest extends TestCase
{
    public function test_an_action_without_a_variant_stays_primary(): void
    {
        $this->assertSame('btn btn-sm btn-primary', (new Action('Edit', '/edit'))->buttonClass());
        $this->assertSame('btn btn-sm btn-primary', (new Action('Edit', '/edit', ''))->buttonClass());
        $this->assertSame('btn btn-sm btn-primary', (new Action('Edit', '/edit', '   '))->buttonClass());
        $this->assertSame('btn btn-sm btn-primary extra', (new Action('Edit', '/edit', 'extra'))->buttonClass());
        $this->assertSame('btn btn-sm btn-primary btn-xs', (new Action('Edit', '/edit', 'btn-xs'))->buttonClass());
        $this->assertSame('btn btn-sm btn-primary btn-sm', (new Action('Edit', '/edit', 'btn-sm'))->buttonClass());
        $this->assertSame('btn btn-sm btn-primary btn-lg', (new Action('Edit', '/edit', 'btn-lg'))->buttonClass());
        $this->assertSame('btn btn-sm btn-primary btn-block', (new Action('Edit', '/edit', 'btn-block'))->buttonClass());
        $this->assertSame(
            'btn btn-sm btn-primary btn-defaultish',
            (new Action('Edit', '/edit', 'btn-defaultish'))->buttonClass(),
        );
    }

    public function test_a_colour_variant_replaces_the_primary_class(): void
    {
        $this->assertSame('btn btn-sm btn-default', (new Action('View', '/view', 'btn-default'))->buttonClass());
        $this->assertSame('btn btn-sm btn-danger', (new Action('Remove', '/remove', 'btn-danger'))->buttonClass());
        $this->assertSame('btn btn-sm btn-secondary', (new Action('Quiet', '/quiet', 'btn-secondary'))->buttonClass());
        $this->assertSame('btn btn-sm btn-success', (new Action('Done', '/done', 'btn-success'))->buttonClass());
        $this->assertSame('btn btn-sm btn-info', (new Action('Note', '/note', 'btn-info'))->buttonClass());
        $this->assertSame('btn btn-sm btn-warning', (new Action('Warn', '/warn', 'btn-warning'))->buttonClass());
        $this->assertSame('btn btn-sm btn-link', (new Action('Open', '/open', 'btn-link'))->buttonClass());
        $this->assertSame(
            'btn btn-sm btn-brand-primary',
            (new Action('Brand', '/brand', 'btn-brand-primary'))->buttonClass(),
        );
        $this->assertSame(
            'btn btn-sm btn-brand-secondary',
            (new Action('Brand', '/brand', 'btn-brand-secondary'))->buttonClass(),
        );
        $this->assertSame('btn btn-sm btn-primary', (new Action('Edit', '/edit', 'btn-primary'))->buttonClass());
        $this->assertSame(
            'btn btn-sm btn-xs btn-default',
            (new Action('View', '/view', 'btn-xs btn-default'))->buttonClass(),
        );
    }
}
