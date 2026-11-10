<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every field that stores a value carries canRestore="1", whatever its scope.
 *
 * canRestore is what renders "Use system value", and that is as useful on a global field as on a
 * scoped one: without it a merchant who changes a setting has no way back to the shipped default
 * short of editing the database. Magento's own modules put it on global fields throughout
 * (page_cache ttl, translate_strategy, inventory batch_size), so this is their rule, not ours.
 *
 * This gate read showInWebsite/showInStore for its first few releases and so never looked at a
 * global field at all; 28 of them across three modules turned out to be missing it.
 */
class CanRestoreTest extends TestCase
{
    public function testEveryFieldThatStoresAValueCanBeRestored(): void
    {
        $missing = [];
        foreach ($this->systemFiles() as $file) {
            $dom = new \DOMDocument();
            $dom->load($file);
            foreach ($dom->getElementsByTagName('field') as $field) {
                if (!$this->isFieldDeclaration($field) || !$this->storesAValue($field)) {
                    continue;
                }
                if ($field->getAttribute('canRestore') !== '1') {
                    $missing[] = basename($file) . ':' . $field->getAttribute('id');
                }
            }
        }
        $this->assertSame([], $missing, 'fields without canRestore="1"');
    }

    public function testNothingThatStoresNoValueOffersToRestoreIt(): void
    {
        $pointless = [];
        foreach ($this->systemFiles() as $file) {
            $dom = new \DOMDocument();
            $dom->load($file);
            foreach ($dom->getElementsByTagName('field') as $field) {
                if ($field->getAttribute('canRestore') === '1' && !$this->storesAValue($field)) {
                    $pointless[] = basename($file) . ':' . $field->getAttribute('id');
                }
            }
        }
        $this->assertSame([], $pointless, 'canRestore="1" on a heading, a button or another renderer');
    }

    /**
     * A <field> inside a <depends> names another field, it does not declare one. Only a real
     * declaration carries a scope attribute.
     */
    private function isFieldDeclaration(\DOMElement $field): bool
    {
        return $field->hasAttribute('showInDefault')
            || $field->hasAttribute('showInWebsite')
            || $field->hasAttribute('showInStore');
    }

    /**
     * Some fields are elements rather than settings: a heading, a button, a result pane, a version
     * check. They are declared as fields only so that system.xml can place them, and "Use Default"
     * beside one restores nothing.
     *
     * What separates them is not the renderer but what the field is backed by. A plain field with
     * no frontend_model always stores its value at section/group/field. A field that brings its own
     * renderer stores something only when it also declares where that value lives or comes from: a
     * backend_model (the field arrays), a source_model (a styled select) or a config_path.
     */
    private function storesAValue(\DOMElement $field): bool
    {
        if (in_array($field->getAttribute('type'), ['button', 'label'], true)) {
            return false;
        }
        // An upload field stores a value, but Magento's own image and file fields carry no
        // canRestore: the ticked inherit checkbox would grey out the upload control itself.
        if (in_array($field->getAttribute('type'), ['file', 'image'], true)) {
            return false;
        }
        if ($this->isDynamicRows($field)) {
            return false;
        }
        if ($field->getElementsByTagName('frontend_model')->length === 0) {
            return true;
        }
        foreach (['source_model', 'backend_model', 'config_path'] as $backing) {
            if ($field->getElementsByTagName($backing)->length > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * A dynamic-rows table: its own renderer plus a backend model that serialises the rows.
     *
     * It stores a value, but it must not carry canRestore all the same. Magento renders the
     * inherit checkbox ticked at website and store scope, which greys out the whole table
     * including its "Add" button, so a merchant cannot add a row until they find and untick a
     * checkbox beside it. Magento's own modules ship no field arrays in system.xml, so there is
     * no precedent to follow; this is the house rule.
     */
    private function isDynamicRows(\DOMElement $field): bool
    {
        return $field->getElementsByTagName('frontend_model')->length > 0
            && $field->getElementsByTagName('backend_model')->length > 0;
    }

    /**
     * system.xml and every file it includes, wherever a module keeps them (etc/adminhtml/system/,
     * or one directory per section as this module does): the <include path> elements decide.
     *
     * @return string[]
     */
    private function systemFiles(): array
    {
        $root = dirname(__DIR__, 3) . '/etc/adminhtml';
        $files = glob($root . '/system.xml') ?: [];
        foreach ($files as $main) {
            $dom = new \DOMDocument();
            $dom->load($main);
            foreach ($dom->getElementsByTagName('include') as $include) {
                /** @var \DOMElement $include */
                $file = $root . '/' . (string)preg_replace('#^[^:]+::#', '', $include->getAttribute('path'));
                if (is_file($file)) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }
}
