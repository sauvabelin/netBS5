<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Model;

use NetBS\CoreBundle\Model\XEditable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class XEditableTest extends TestCase
{
    public function testUncheckedSwitchSubmitsNull(): void
    {
        // CheckboxType treats every non-null value as checked, so '' would save true.
        $this->assertNull($this->switch([''])->getFinalValue());
    }

    public function testCheckedSwitchSubmitsOne(): void
    {
        $this->assertSame('1', $this->switch(['1'])->getFinalValue());
    }

    /**
     * @param list<string> $value what editable_controller.js posts as value[]
     */
    private function switch(array $value): XEditable
    {
        return new XEditable(new Request(request: [
            'pk'    => '1',
            'name'  => json_encode(['field' => 'wikiAccount', 'type' => 'switch']),
            'value' => $value,
        ]));
    }
}
