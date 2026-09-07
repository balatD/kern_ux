<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Form\Domain\Configuration\ConfigurationService;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractSection;
use TYPO3\CMS\Form\Domain\Model\FormElements\Section;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders whole forms and asserts on the markup.
 *
 * The form theme was the one subsystem with no rendering test, and every defect this
 * class pins had survived in it: an empty confirmation step, a required group that
 * announced nothing, and `autocomplete` silently dropped from all 30 element partials.
 * None of them were reachable from a parse-only test - `{summaryPageElements}` is
 * perfectly valid Fluid, it just resolves to nothing - and none were reachable from a
 * unit test either, because the bugs live in the seam between the partials, the
 * ViewHelpers and ext:form's own runtime.
 *
 * The honeypot is off on these fixtures: FormRuntime stores its field name in the
 * frontend user session, and standing one up here would buy nothing this test is about.
 */
final class FormMarkupTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    private ServerRequest $serverRequest;

    /**
     * The frontend request has to exist before anything asks for the prototype: ext:form
     * reads it through Extbase's ConfigurationManager, which resolves the request from
     * $GLOBALS and throws without one.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $site = new Site('kern', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);

        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupTree(new RootNode());
        $typoScript->setSetupArray($this->formTypoScript());
        $typoScript->setConfigTree(new RootNode());
        $typoScript->setConfigArray([]);

        $this->serverRequest = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage())
            ->withAttribute('frontend.typoscript', $typoScript)
            ->withAttribute('frontend.user', new FrontendUserAuthentication())
            ->withAttribute('extbase', new ExtbaseRequestParameters());

        $GLOBALS['TYPO3_REQUEST'] = $this->serverRequest;
    }

    #[Test]
    public function passesTheElementsAutocompleteThroughToTheControl(): void
    {
        $form = $this->form();
        $name = $this->element($form->createPage('page1'), 'name', 'Text', 'Name');
        $name->setProperty('fluidAdditionalAttributes', ['autocomplete' => 'name']);

        // WCAG 1.3.5 is satisfiable only through this attribute, and the partials used
        // to replace additionalAttributes outright with the ARIA map, dropping it.
        self::assertStringContainsString('autocomplete="name"', $this->render($form));
    }

    #[Test]
    public function passesOtherEditorSuppliedAttributesThroughToo(): void
    {
        $form = $this->form();
        $element = $this->element($form->createPage('page1'), 'street', 'Text', 'Street');
        $element->setProperty('fluidAdditionalAttributes', [
            'placeholder' => 'Musterstraße 1',
            'maxlength' => '80',
        ]);

        $html = $this->render($form);
        self::assertStringContainsString('placeholder="Musterstraße 1"', $html);
        self::assertStringContainsString('maxlength="80"', $html);
    }

    #[Test]
    public function keepsItsOwnAriaWhenAnEditorSuppliesTheSameAttribute(): void
    {
        $form = $this->form();
        $element = $this->element($form->createPage('page1'), 'name', 'Text', 'Name');
        $element->createValidator('NotEmpty');
        // An editor must not be able to unhook the accessibility contract by hand.
        $element->setProperty('fluidAdditionalAttributes', [
            'aria-required' => 'false',
            'aria-describedby' => 'somewhere-else',
            'autocomplete' => 'name',
        ]);

        $html = $this->render($form);
        self::assertStringContainsString('aria-required="true"', $html);
        self::assertStringNotContainsString('aria-required="false"', $html);
        self::assertStringNotContainsString('somewhere-else', $html);
        self::assertStringContainsString('autocomplete="name"', $html);
    }

    #[Test]
    public function announcesARequiredFieldAsRequiredAndLeavesItUnmarked(): void
    {
        $form = $this->form();
        $element = $this->element($form->createPage('page1'), 'name', 'Text', 'Name');
        $element->createValidator('NotEmpty');

        $html = $this->render($form);
        self::assertStringContainsString('aria-required="true"', $html);
        // KERN inverts the convention: the optional minority carries the marker.
        self::assertStringNotContainsString('kern-label__optional', $html);
        // aria-required stands in for the native attribute, which brings browser UI
        // that cannot be styled.
        self::assertStringNotContainsString(' required="required"', $html);
    }

    #[Test]
    public function marksAnOptionalFieldAsOptional(): void
    {
        $form = $this->form();
        $this->element($form->createPage('page1'), 'nickname', 'Text', 'Nickname');

        $html = $this->render($form);
        self::assertStringContainsString('kern-label__optional', $html);
        self::assertStringNotContainsString('aria-required', $html);
    }

    #[Test]
    public function announcesARequiredRadioGroupAsRequiredOnTheFieldset(): void
    {
        $form = $this->form();
        $element = $this->element($form->createPage('page1'), 'salutation', 'RadioButton', 'Salutation');
        $element->setProperty('options', ['mr' => 'Mr', 'mrs' => 'Mrs']);
        $element->createValidator('NotEmpty');

        $html = $this->render($form);

        // The fieldset is what carries the group's accessible name, and it maps to
        // role="group", which supports the attribute. Before this the group was
        // required visually and silent to assistive technology.
        self::assertMatchesRegularExpression('/<fieldset[^>]*aria-required="true"/', $html);
        self::assertStringNotContainsString('kern-label__optional', $html);
    }

    #[Test]
    public function wiresTheHintOntoTheControlWithAriaDescribedby(): void
    {
        $form = $this->form();
        $element = $this->element($form->createPage('page1'), 'name', 'Text', 'Name');
        $element->setProperty('elementDescription', 'As written in your passport.');

        $html = $this->render($form);
        self::assertStringContainsString('id="kernform-name-hint"', $html);
        self::assertStringContainsString('As written in your passport.', $html);
        // The hint comes first and the error second, which is the order KERN's plain kit
        // uses; with no error yet there is only the hint to point at.
        self::assertStringContainsString('aria-describedby="kernform-name-hint"', $html);
    }

    #[Test]
    public function rendersEveryAnsweredValueOnTheSummaryPage(): void
    {
        $form = $this->form();
        $first = $form->createPage('page1');
        $this->element($first, 'name', 'Text', 'Name');
        $this->element($first, 'email', 'Text', 'E-Mail');
        $form->createPage('confirmation', 'SummaryPage')->setLabel('Check your answers');

        $html = $this->renderSummary($form, [
            'name' => 'Erika Mustermann',
            'email' => 'e.mustermann@example.org',
        ]);

        // The template used to loop a variable that does not exist, so the summary was
        // the KERN shell around an empty definition list - on every multi-page form.
        self::assertStringContainsString('kern-summary', $html);
        self::assertStringContainsString('Erika Mustermann', $html);
        self::assertStringContainsString('e.mustermann@example.org', $html);
        self::assertStringContainsString('Name', $html);
        self::assertStringContainsString('E-Mail', $html);
        self::assertStringContainsString('kern-description-list-item__key', $html);
    }

    #[Test]
    public function formatsAKernDateOnTheSummaryPageInsteadOfConcatenatingItsParts(): void
    {
        $form = $this->form();
        $this->element($form->createPage('page1'), 'birthday', 'KernDate', 'Date of birth');
        $form->createPage('confirmation', 'SummaryPage')->setLabel('Check your answers');

        $html = $this->renderSummary($form, [
            'birthday' => ['day' => '12', 'month' => '8', 'year' => '1964'],
        ]);

        self::assertStringContainsString('12.08.1964', $html);
        // The parts are an array, and isMultiValue is true for anything iterable, so
        // without its own branch the date came out as a bullet list of digits.
        self::assertStringNotContainsString('1281964', $html);
    }

    #[Test]
    public function showsAPlaceholderForAnUnansweredFieldOnTheSummaryPage(): void
    {
        $form = $this->form();
        $this->element($form->createPage('page1'), 'nickname', 'Text', 'Nickname');
        $form->createPage('confirmation', 'SummaryPage')->setLabel('Check your answers');

        $html = $this->renderSummary($form, []);
        self::assertStringContainsString('Nickname', $html);
        self::assertStringContainsString('Not provided', $html);
    }

    #[Test]
    public function groupsTheSummaryBySectionUsingKernsOwnGroupHeader(): void
    {
        $form = $this->form();
        $section = $form->createPage('page1')->createElement('personal', 'Fieldset');
        self::assertInstanceOf(Section::class, $section);
        $section->setLabel('Personal details');
        $this->element($section, 'name', 'Text', 'Name');
        $form->createPage('confirmation', 'SummaryPage')->setLabel('Check your answers');

        $html = $this->renderSummary($form, ['name' => 'Erika Mustermann']);

        // A heading is not a permitted child of dl, so the group headers are siblings of
        // the rows - which is also why KERN ships kern-summary-group__header.
        self::assertStringContainsString('kern-summary-group__header', $html);
        self::assertStringContainsString('Personal details', $html);
        self::assertStringContainsString('Erika Mustermann', $html);
    }

    /**
     * The TypoScript ext:form needs in order to find its prototypes.
     *
     * This is the one place the two majors genuinely diverge. On TYPO3 14 a prototype
     * comes from the form sets, which are collected from every active package when the
     * container is built - nothing has to be in TypoScript, so an empty array is right.
     * On 13 form sets do not exist: core registers its own base YAML through
     * plugin.tx_form.settings.yamlConfigurations and ext_localconf.php appends this
     * extension's config the same way. A functional test resolves TypoScript itself, so
     * with an empty setup array ext:form finds no configuration at all there - not even
     * core's - and every prototype lookup fails.
     *
     * The keys mirror the two real registrations: 10 is core's, and the timestamp key is
     * the one ext_localconf.php uses.
     *
     * @return array<string, mixed>
     */
    private function formTypoScript(): array
    {
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            return [];
        }

        return [
            'plugin.' => [
                'tx_form.' => [
                    'settings.' => [
                        'yamlConfigurations.' => [
                            '10' => 'EXT:form/Configuration/Yaml/FormSetup.yaml',
                            '1756140000' => 'EXT:kern_ux/Configuration/Form/KernUx/config.yaml',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Creates and labels an element, typed to the concrete class.
     *
     * createElement() is declared as returning FormElementInterface, but setLabel() and
     * createValidator() live on AbstractRenderable rather than on that interface - so
     * every call site would otherwise need its own assertion to stay analysable.
     */
    private function element(
        AbstractSection $parent,
        string $identifier,
        string $type,
        string $label,
    ): AbstractFormElement {
        $element = $parent->createElement($identifier, $type);
        self::assertInstanceOf(AbstractFormElement::class, $element);
        $element->setLabel($label);

        return $element;
    }

    private function form(): FormDefinition
    {
        $configurationService = $this->get(ConfigurationService::class);
        self::assertInstanceOf(ConfigurationService::class, $configurationService);

        $form = new FormDefinition('kernform', $configurationService->getPrototypeConfiguration('standard'));
        $form->setRenderingOption('honeypot', ['enable' => false]);

        return $form;
    }

    private function render(FormDefinition $form): string
    {
        return (string)$this->runtime($form)->render();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function renderSummary(FormDefinition $form, array $values): string
    {
        $runtime = $this->runtime($form);

        $state = $runtime->getFormState();
        self::assertNotNull($state);
        foreach ($values as $property => $value) {
            $state->setFormValue($property, $value);
        }

        // The summary is the last page; a GET render would otherwise show the first.
        $runtime->overrideCurrentPage(count($form->getPages()) - 1);

        return (string)$runtime->render();
    }

    private function runtime(FormDefinition $form): FormRuntime
    {
        return $form->bind(new Request($this->serverRequest));
    }
}
