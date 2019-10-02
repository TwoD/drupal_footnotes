<?php

namespace Drupal\Tests\footnotes\FunctionalJavascript;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Contains Footnotes CKEditor plugin functionality tests.
 *
 * @group footnotes
 */
class FootnotesCkeditorPluginTest extends WebDriverTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'fakeobjects',
    'footnotes',
    'node',
  ];

  /**
   * An user with permissions to proper permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Text format name.
   *
   * @var string
   */
  protected $formatName;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);

    // Create a filter admin user.
    $permissions = [
      'administer filters',
      'administer nodes',
      'edit own page content',
      'create page content',
      'access administration pages',
      'administer site configuration',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
    $this->formatName = strtolower($this->randomMachineName());

    $this->drupalLogin($this->adminUser);
    $this->createTextFormat();
  }

  /**
   * Tests CKEditor plugin functionality.
   *
   * @todo add tests for CKEditor filter settings.
   */
  public function testFootnotesCkeditorPlugin() {
    $this->checkCkeditor();
  }

  /**
   * Create a new text format.
   *
   * @param bool $additional_settings
   *   Indicates if filter settings should be enabled.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function createTextFormat($additional_settings = FALSE) {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $button_groups = json_encode([
      [
        [
          'name' => 'Tools',
          'items' => ['Source', 'footnotes'],
        ],
      ],
    ]);

    $this->drupalGet("admin/config/content/formats/add");
    // Fill in a label to the media type.
    $page->fillField('name', $this->formatName);
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '.machine-name-value')
    );
    $page->checkField('roles[' . AccountInterface::AUTHENTICATED_ROLE . ']');
    $page->selectFieldOption('editor[editor]', 'ckeditor');
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '.ckeditor-toolbar-configuration')
    );
    $session->executeScript("jQuery('.form-item-editor-settings-toolbar-button-groups').css('display', 'block');");
    $page->fillField('editor[settings][toolbar][button_groups]', $button_groups);
    $page->checkField('filters[filter_footnotes][status]');
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '[data-drupal-selector="edit-filters-filter-footnotes-settings"]')
    );
    if ($additional_settings) {
      $page->checkField('filters[filter_footnotes][settings][footnotes_collapse]');
      $page->checkField('filters[filter_footnotes][settings][footnotes_html]');
    }
    $page->pressButton('Save configuration');
    $assert_session->pageTextContains($this->t('Added text format @format.', ['@format' => $this->formatName]));
  }

  /**
   * Tests CKEditor plugin functionality for body field.
   */
  protected function checkCkeditor() {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet("node/add/page");
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '.cke_1.cke')
    );
    $assert_session->elementExists('css', '.cke .cke_button__footnotes')->click();
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '.cke_1.cke_editor_edit-body-0-value_dialog')
    );
    $assert_session->elementTextContains('css', 'table.cke_dialog .cke_dialog_title', $this->t('Footnotes Dialog'));
    $assert_session->elementTextContains('css', '.cke_dialog_page_contents table tr:first-child', $this->t('Footnote text :'));
    $assert_session->elementTextContains('css', '.cke_dialog_page_contents table tr:last-child', $this->t('Value :'));
    $page->find('css', 'a.cke_dialog_ui_button_cancel')->click();

    $this->assertEmpty($assert_session->elementExists('css', '.cke_1.cke_editor_edit-body-0-value_dialog')->isVisible());

    $texts = ['Text one.', 'Text two.', 'Text tree', 'Text four', 'Text five'];
    foreach ($texts as $key => $value) {
      $assert_session->elementExists('css', '.cke .cke_button__footnotes')->click();
      $this->assertNotEmpty(
        $assert_session->waitForElementVisible('css', '.cke_1.cke_editor_edit-body-0-value_dialog')
      );
      $assert_session->elementExists('css', '.cke_dialog_page_contents table tr:last-child input')->setValue($key);
      $assert_session->elementExists('css', '.cke_dialog_page_contents table tr:first-child input')->setValue($value);
      $page->find('css', 'a.cke_dialog_ui_button_ok')->click();

      $this->assertEmpty($assert_session->elementExists('css', '.cke_1.cke_editor_edit-body-0-value_dialog')->isVisible());
    }
    $assert_session->elementExists('css', '.cke .cke_button__source')->click();
    $body_value = $assert_session->elementExists('css', '.cke .cke_contents .cke_source')->getValue();

    $body_value = str_replace(["\r\n", "\r", "\n"], "", $body_value);
    $body_value = trim($body_value);

    $expected_value = '<p>';
    foreach ($texts as $key => $value) {
      $expected_value .= '<fn value="' . $key . '">' . $value . '</fn>';
    }
    $expected_value .= '</p>';

    $this->assertEqual($body_value, $expected_value, $this->t('String, formed by CKEditor, is correct.'));
  }

}
