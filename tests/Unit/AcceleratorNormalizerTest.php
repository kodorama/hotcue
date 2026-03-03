<?php

namespace Tests\Unit;

use App\Services\Hotkeys\AcceleratorNormalizer;
use PHPUnit\Framework\TestCase;

class AcceleratorNormalizerTest extends TestCase
{
    public function test_normalizes_simple_key(): void
    {
        $result = AcceleratorNormalizer::normalize('a');
        $this->assertEquals('a', $result);
    }

    public function test_normalizes_with_single_modifier(): void
    {
        $result = AcceleratorNormalizer::normalize('cmd+a');
        $this->assertEquals('cmd+a', $result);
    }

    public function test_normalizes_modifier_order(): void
    {
        $result = AcceleratorNormalizer::normalize('shift+alt+ctrl+cmd+a');
        $this->assertEquals('cmd+ctrl+alt+shift+a', $result);
    }

    public function test_normalizes_case_insensitive(): void
    {
        $result = AcceleratorNormalizer::normalize('CMD+SHIFT+A');
        $this->assertEquals('cmd+shift+a', $result);
    }

    public function test_normalizes_command_alias(): void
    {
        $result = AcceleratorNormalizer::normalize('command+a');
        $this->assertEquals('cmd+a', $result);
    }

    public function test_normalizes_control_alias(): void
    {
        $result = AcceleratorNormalizer::normalize('control+a');
        $this->assertEquals('ctrl+a', $result);
    }

    public function test_normalizes_option_alias(): void
    {
        $result = AcceleratorNormalizer::normalize('option+a');
        $this->assertEquals('alt+a', $result);
    }

    public function test_normalizes_with_spaces(): void
    {
        $result = AcceleratorNormalizer::normalize('cmd + shift + a');
        $this->assertEquals('cmd+shift+a', $result);
    }

    public function test_normalizes_function_key(): void
    {
        $result = AcceleratorNormalizer::normalize('cmd+f13');
        $this->assertEquals('cmd+f13', $result);
    }

    public function test_normalizes_special_keys(): void
    {
        $result = AcceleratorNormalizer::normalize('cmd+space');
        $this->assertEquals('cmd+space', $result);

        $result = AcceleratorNormalizer::normalize('shift+up');
        $this->assertEquals('shift+up', $result);
    }

    public function test_normalizes_all_modifiers(): void
    {
        $result = AcceleratorNormalizer::normalize('alt+shift+cmd+ctrl+1');
        $this->assertEquals('cmd+ctrl+alt+shift+1', $result);
    }

    public function test_to_native_format(): void
    {
        $normalized = AcceleratorNormalizer::normalize('cmd+shift+a');
        $result = AcceleratorNormalizer::toNativeFormat($normalized);
        $this->assertEquals('Cmd+Shift+A', $result);
    }

    public function test_to_native_format_all_modifiers(): void
    {
        $normalized = AcceleratorNormalizer::normalize('cmd+ctrl+alt+shift+1');
        $result = AcceleratorNormalizer::toNativeFormat($normalized);
        $this->assertEquals('Cmd+Ctrl+Alt+Shift+1', $result);
    }

    public function test_to_native_format_function_key(): void
    {
        $normalized = AcceleratorNormalizer::normalize('cmd+f13');
        $result = AcceleratorNormalizer::toNativeFormat($normalized);
        $this->assertEquals('Cmd+F13', $result);
    }

    public function test_to_native_format_special_key_space(): void
    {
        $normalized = AcceleratorNormalizer::normalize('cmd+space');
        $result = AcceleratorNormalizer::toNativeFormat($normalized);
        $this->assertEquals('Cmd+Space', $result);
    }

    public function test_to_native_format_arrow_key(): void
    {
        $normalized = AcceleratorNormalizer::normalize('shift+up');
        $result = AcceleratorNormalizer::toNativeFormat($normalized);
        $this->assertEquals('Shift+Up', $result);
    }

    public function test_to_native_format_bare_function_key(): void
    {
        $normalized = AcceleratorNormalizer::normalize('f5');
        $result = AcceleratorNormalizer::toNativeFormat($normalized);
        $this->assertEquals('F5', $result);
    }
}
