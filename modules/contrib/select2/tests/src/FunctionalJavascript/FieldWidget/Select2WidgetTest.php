<?php

namespace Drupal\Tests\select2\FunctionalJavascript\FieldWidget;

use Drupal\Tests\select2\FunctionalJavascript\Select2JavascriptTestBase;

/**
 * Tests select2 simple widget.
 *
 * @group select2
 */
class Select2WidgetTest extends Select2JavascriptTestBase {

  /**
   * Test single field selection.
   *
   * @group select2
   */
  public function testSingleSelect() {
    $this->createField('select2', 'node', 'test', 'list_string', [
      'allowed_values' => [
        'foo' => 'Foo',
        'bar' => 'Bar',
      ],
    ], [], 'select2', []);

    $page = $this->getSession()->getPage();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');
    $this->selectOption('edit-select2', ['foo']);
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node');
    $this->assertArraySubset([['value' => 'foo']], $node->select2->getValue());

    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->elementExists('css', '.form-item-select2 .select2-selection__clear');
    $this->click('.form-item-select2 .select2-selection__clear');
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertArraySubset([], $node->select2->getValue());

    $this->drupalGet($node->toUrl('edit-form'));
    $this->selectOption('edit-select2', ['bar']);
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertArraySubset([['value' => 'bar']], $node->select2->getValue());
  }

  /**
   * Test single field selection.
   */
  public function testSingleSelectRequired() {
    $this->createField('select2', 'node', 'test', 'list_string', [
      'allowed_values' => [
        'foo' => 'Foo',
        'bar' => 'Bar',
      ],
    ], ['required' => TRUE], 'select2', []);

    $page = $this->getSession()->getPage();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');
    $this->selectOption('edit-select2', ['foo']);
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node');
    $this->assertArraySubset([['value' => 'foo']], $node->select2->getValue());

    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->elementNotExists('css', '.form-item-select2 .select2-selection__clear');
    $this->selectOption('edit-select2', ['bar']);
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertArraySubset([['value' => 'bar']], $node->select2->getValue());
  }

  /**
   * Test multiple field selection with unlimited items.
   */
  public function testMultipleSelect() {
    $this->createField('select2', 'node', 'test', 'list_string', [
      'allowed_values' => [
        'foo' => 'Foo',
        'bar' => 'Bar',
        'gaga' => 'Gaga',
      ],
      'cardinality' => -1,
    ], [], 'select2', []);

    $page = $this->getSession()->getPage();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');
    $this->selectOption('edit-select2', ['foo', 'gaga']);
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node');
    $this->assertArraySubset([['value' => 'foo'], ['value' => 'gaga']], $node->select2->getValue());

    $this->drupalGet($node->toUrl('edit-form'));
    $this->selectOption('edit-select2', []);
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertArraySubset([], $node->select2->getValue());

    $this->drupalGet($node->toUrl('edit-form'));
    $this->selectOption('edit-select2', ['bar']);
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertArraySubset([['value' => 'bar']], $node->select2->getValue());
  }

  /**
   * Test multiple field selection with 2 items.
   */
  public function testLimitedCount() {
    $this->createField('select2', 'node', 'test', 'list_string', [
      'allowed_values' => [
        'foo' => 'Foo',
        'bar' => 'Bar',
        'gaga' => 'Gaga',
      ],
      'cardinality' => 2,
    ], [], 'select2', []);

    $page = $this->getSession()->getPage();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');
    $this->selectOption('edit-select2', ['foo', 'gaga']);
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node');
    $this->assertArraySubset([['value' => 'foo'], ['value' => 'gaga']], $node->select2->getValue());

    $this->drupalGet($node->toUrl('edit-form'));

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $this->assertSession()->elementTextContains('css', '.select2-dropdown.select2-dropdown--below', 'You can only select 2 items');
  }

}
