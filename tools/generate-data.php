<?php

/**
 * Generates the `Danestves\LaravelPolar\Data` DTOs and `Danestves\LaravelPolar\Enums`
 * backed enums from Polar's published OpenAPI document.
 *
 * Polar deprecated their PHP SDK (polarsource/polar-php) in July 2026 and now recommend
 * talking to the API over raw HTTP. Rather than hand-maintain hundreds of response shapes,
 * this script derives them from the same spec Polar publishes, so a Polar API change is a
 * one-command regeneration instead of an archaeology session.
 *
 * Usage:
 *   php tools/generate-data.php [path-or-url-to-openapi.json]
 *
 * Defaults to https://docs.polar.sh/openapi.json, the document Polar publishes today. The
 * generated files are committed to the repository; this script is a maintenance tool, never a
 * runtime dependency.
 */

const SPEC_DEFAULT = 'https://docs.polar.sh/openapi.json';
const DATA_NS = 'Danestves\\LaravelPolar\\Data';
const ENUM_NS = 'Danestves\\LaravelPolar\\Enums';

/**
 * Schemas the package actually surfaces. Everything else is pulled in transitively, so this
 * list stays as small as the public API allows. Add a root only when the package starts
 * returning or accepting that shape.
 */
const ROOTS = [
    // Responses.
    'Checkout', 'CheckoutLink', 'Subscription', 'Order', 'Product', 'CustomerSession',
    'Benefit', 'BenefitGrant', 'BenefitGrantWebhook', 'EventsIngestResponse', 'CustomerMeter',
    'MetricsResponse', 'FileRead', 'Organization', 'SeatsList', 'CustomerSeat',
    'LicenseKeyRead', 'LicenseKeyWithActivations', 'ValidatedLicenseKey',
    'LicenseKeyActivationRead', 'CustomField', 'Discount', 'Refund', 'CustomerPaymentMethod',
    'CustomerOrderInvoice', 'Customer', 'CustomerState', 'Pagination',
    // Requests.
    'CheckoutCreate', 'SubscriptionUpdate', 'CustomerSessionCustomerIDCreate',
    'CustomerSessionCustomerExternalIDCreate', 'BenefitCreate', 'EventsIngest',
    'LicenseKeyUpdate', 'LicenseKeyValidate', 'LicenseKeyActivate', 'LicenseKeyDeactivate',
    'CustomFieldCreate', 'CustomFieldUpdate', 'CheckoutLinkCreate', 'CheckoutLinkUpdate',
    'DiscountCreate', 'DiscountUpdate', 'RefundCreate', 'SeatAssign',
    'BenefitCustomUpdate', 'BenefitDiscordUpdate', 'BenefitGitHubRepositoryUpdate',
    'BenefitDownloadablesUpdate', 'BenefitLicenseKeysUpdate', 'BenefitMeterCreditUpdate',
    'BenefitFeatureFlagUpdate', 'BenefitSlackSharedChannelUpdate',
    // Webhook payloads, one per event the package dispatches.
    'WebhookOrderCreatedPayload', 'WebhookOrderUpdatedPayload',
    'WebhookSubscriptionCreatedPayload', 'WebhookSubscriptionUpdatedPayload',
    'WebhookSubscriptionActivePayload', 'WebhookSubscriptionCanceledPayload',
    'WebhookSubscriptionRevokedPayload', 'WebhookBenefitGrantCreatedPayload',
    'WebhookBenefitGrantUpdatedPayload', 'WebhookBenefitGrantRevokedPayload',
    'WebhookCheckoutCreatedPayload', 'WebhookCheckoutUpdatedPayload',
    'WebhookCheckoutExpiredPayload', 'WebhookCustomerCreatedPayload',
    'WebhookCustomerUpdatedPayload', 'WebhookCustomerDeletedPayload',
    'WebhookCustomerStateChangedPayload', 'WebhookProductCreatedPayload',
    'WebhookProductUpdatedPayload', 'WebhookBenefitCreatedPayload',
    'WebhookBenefitUpdatedPayload',
    // Query-parameter enums the package exposes.
    'ProductSortProperty', 'TimeInterval',
];

/**
 * A handful of Polar unions cannot be resolved from the document alone: the spec declares no
 * discriminator and the members are told apart by enum combinations or by a nested field.
 * These overrides supply that missing knowledge, keeping everything else spec-driven.
 *
 * - `morphKeys`      properties to tag with #[PropertyForMorph] so laravel-data hands them to morph()
 * - `morphOptional`  morph keys the payload may legitimately omit; the base declares them
 *                    nullable with a null default, since laravel-data refuses to morph at all
 *                    when a morph key is neither present nor defaulted
 * - `morphBody`      the body of the generated morph() method
 * - `factory`        an extra static factory, for unions keyed on a nested field (morph() only
 *                    sees top-level scalars, so nested discrimination needs an explicit entry point)
 */
const UNION_OVERRIDES = [
    // `type` and `duration` are plain enums on every member; the pairing selects the class.
    'Discount' => [
        'morphKeys' => ['type', 'duration'],
        'uses' => [ENUM_NS . '\\DiscountDuration', ENUM_NS . '\\DiscountType'],
        'morphBody' => <<<'PHP'
                $repeating = $properties['duration'] === DiscountDuration::Repeating;

                return match ($properties['type']) {
                    DiscountType::Fixed => $repeating
                        ? DiscountFixedRepeatDuration::class
                        : DiscountFixedOnceForeverDuration::class,
                    DiscountType::Percentage => $repeating
                        ? DiscountPercentageRepeatDuration::class
                        : DiscountPercentageOnceForeverDuration::class,
                    default => null,
                };
        PHP,
    ],
    // Only the card variant pins `type`; anything else is the generic shape.
    'CustomerPaymentMethod' => [
        'morphKeys' => ['type'],
        'uses' => [],
        'morphBody' => <<<'PHP'
                return $properties['type'] === 'card'
                    ? PaymentMethodCard::class
                    : PaymentMethodGeneric::class;
        PHP,
    ],
    // Polar exposes prices as `LegacyRecurringProductPrice|ProductPrice`, and both halves key on
    // `amount_type` — so `fixed` and `custom` are ambiguous, and `seat_based`/`metered_unit` only
    // exist on the modern half. laravel-data resolves a union property to its *first* data class
    // and never tries the others, so whichever base wins has to answer for the whole field.
    // Only legacy prices carry `type: "recurring"`; everything else is handed to ProductPrice.
    'LegacyRecurringProductPrice' => [
        'morphKeys' => ['amount_type', 'type'],
        // Modern prices dropped `type` entirely, so the base cannot require it.
        'morphOptional' => ['type'],
        'uses' => [],
        'morphBody' => <<<'PHP'
                if ($properties['type'] !== 'recurring') {
                    return ProductPrice::morph($properties);
                }

                return match ($properties['amountType']) {
                    'custom' => LegacyRecurringProductPriceCustom::class,
                    'fixed' => LegacyRecurringProductPriceFixed::class,
                    default => null,
                };
        PHP,
    ],
    // Benefit grants are keyed on the nested benefit's type, which morph() cannot reach.
    'BenefitGrantWebhook' => [
        'morphKeys' => [],
        'uses' => [],
        'morphBody' => null,
        'factory' => <<<'PHP'
            /**
             * Build the concrete grant from a webhook payload.
             *
             * Benefit grants are discriminated by the nested benefit's type rather than a
             * top-level field, so they are resolved here instead of through morph().
             *
             * Deliberately not named `from...`: laravel-data treats any static `from*` method as
             * a magical constructor, which would make this call itself forever.
             *
             * @param  array<string, mixed>  $payload
             */
            public static function resolve(array $payload): self
            {
                $benefit = $payload['benefit'] ?? [];
                $type = is_array($benefit) ? ($benefit['type'] ?? null) : null;

                return match ($type) {
                    'discord' => BenefitGrantDiscordWebhook::from($payload),
                    'github_repository' => BenefitGrantGitHubRepositoryWebhook::from($payload),
                    'downloadables' => BenefitGrantDownloadablesWebhook::from($payload),
                    'license_keys' => BenefitGrantLicenseKeysWebhook::from($payload),
                    'meter_credit' => BenefitGrantMeterCreditWebhook::from($payload),
                    'feature_flag' => BenefitGrantFeatureFlagWebhook::from($payload),
                    'slack_shared_channel' => BenefitGrantSlackSharedChannelWebhook::from($payload),
                    default => BenefitGrantCustomWebhook::from($payload),
                };
            }
        PHP,
    ],
];

/**
 * Enums the spec declares inline on a property rather than as a named schema. Naming them
 * keeps validation exact instead of degrading the field to a bare string.
 *
 * Keyed by "Schema.property" => generated enum name.
 */
const INLINE_ENUMS = [
    'AddressInput.country' => 'CountryAlpha2',
    'Address.country' => 'CountryAlpha2',
    // Deliberately not the `RefundReason` response enum: that one also carries
    // `dispute_prevention`, which Polar will not accept when creating a refund.
    'RefundCreate.reason' => 'RefundCreateReason',
];

/**
 * Response shapes the test suite hydrates. The generator emits a spec-valid skeleton for each
 * so tests can say `PolarFixtures::make('Subscription', ['status' => 'active'])` instead of
 * hand-maintaining 35 required fields.
 */
const FIXTURE_ROOTS = [
    'Checkout', 'CheckoutLink', 'Subscription', 'Order', 'Product', 'CustomerSession',
    'CustomerMeter', 'Organization', 'SeatsList', 'CustomerSeat', 'LicenseKeyRead',
    'LicenseKeyWithActivations', 'ValidatedLicenseKey', 'LicenseKeyActivationRead',
    'Refund', 'Pagination', 'CustomerOrderInvoice', 'EventsIngestResponse', 'MetricsResponse',
    'CustomerIndividual', 'CustomerStateIndividual', 'BenefitCustom', 'BenefitGrantCustomWebhook',
    'CustomFieldText', 'DiscountFixedOnceForeverDuration', 'PaymentMethodCard',
    'DownloadableFileRead',
    // Every price shape, so the `LegacyRecurringProductPrice|ProductPrice` union stays covered.
    'ProductPriceFixed', 'ProductPriceCustom', 'ProductPriceSeatBased', 'ProductPriceMeteredUnit',
    'LegacyRecurringProductPriceFixed', 'LegacyRecurringProductPriceCustom',
];

$specPath = $argv[1] ?? SPEC_DEFAULT;
fwrite(STDERR, "Reading spec: {$specPath}\n");
$raw = file_get_contents($specPath);
if ($raw === false) {
    fwrite(STDERR, "Failed to read spec.\n");
    exit(1);
}
$spec = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
$schemas = $spec['components']['schemas'];
$schemaCount = count($schemas);
fwrite(STDERR, "Spec version: {$spec['info']['version']}, {$schemaCount} schemas\n");

$generator = new DataGenerator($schemas);
$generator->run(__DIR__ . '/..');

// ---------------------------------------------------------------------------

final class DataGenerator
{
    /** @var array<string, array<string, mixed>> */
    private array $schemas;

    /** @var array<string, true> Schema names pulled into the generated closure. */
    private array $closure = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(array $schemas)
    {
        $this->schemas = $schemas;
    }

    public function run(string $root): void
    {
        foreach (ROOTS as $name) {
            $this->collect($name);
        }

        $this->promoteInlineEnums();

        $dataDir = $root . '/src/Data';
        $enumDir = $root . '/src/Enums';
        $this->resetDir($dataDir);
        $this->resetDir($enumDir);

        $dataCount = $enumCount = 0;

        foreach (array_keys($this->closure) as $name) {
            $schema = $this->schemas[$name];
            $class = $this->className($name);

            if ($this->isEnum($schema)) {
                file_put_contents("{$enumDir}/{$class}.php", $this->renderEnum($class, $name, $schema));
                $enumCount++;

                continue;
            }

            if ($this->isUnion($schema)) {
                file_put_contents("{$dataDir}/{$class}.php", $this->renderUnion($class, $name, $schema));
                $dataCount++;

                continue;
            }

            file_put_contents("{$dataDir}/{$class}.php", $this->renderObject($class, $name, $schema, null));
            $dataCount++;
        }

        $this->writeFixtures($root . '/tests/Fixtures/PolarFixtures.php');

        fwrite(STDERR, "Generated {$dataCount} data classes, {$enumCount} enums.\n");

        foreach (array_unique($this->warnings) as $warning) {
            fwrite(STDERR, "  warning: {$warning}\n");
        }
    }

    // -- fixtures -----------------------------------------------------------

    private function writeFixtures(string $path): void
    {
        $entries = [];

        foreach (FIXTURE_ROOTS as $name) {
            $skeleton = $this->fixtureFor(['$ref' => "#/components/schemas/{$name}"], []);
            $entries[] = "        '{$name}' => " . $this->exportArray($skeleton, 3) . ',';
        }

        $body = implode("\n", $entries);

        $contents = <<<PHP
        <?php

        // This file is generated by tools/generate-data.php. Do not edit by hand.

        declare(strict_types=1);

        namespace Danestves\LaravelPolar\Tests\Fixtures;

        /**
         * Spec-valid skeleton payloads for Polar API responses.
         *
         * Every required field is present with a type-appropriate placeholder, so a test only has
         * to state the fields it actually cares about:
         *
         *     PolarFixtures::make('Subscription', ['status' => 'active', 'product_id' => \$id])
         */
        final class PolarFixtures
        {
            /**
             * Build a payload for a Polar schema, merged with the given overrides.
             *
             * @param  array<string, mixed>  \$overrides
             * @return array<string, mixed>
             */
            public static function make(string \$schema, array \$overrides = []): array
            {
                \$payload = self::skeletons()[\$schema]
                    ?? throw new \InvalidArgumentException("No fixture for Polar schema [{\$schema}].");

                return self::merge(\$payload, \$overrides);
            }

            /**
             * Merge overrides into a skeleton.
             *
             * Nested objects merge recursively, so a test can set `['customer' => ['id' => 'x']]`
             * without restating the customer's other required fields. Lists are replaced outright,
             * since a test that supplies items means exactly those items.
             *
             * @param  array<string, mixed>  \$base
             * @param  array<string, mixed>  \$overrides
             * @return array<string, mixed>
             */
            private static function merge(array \$base, array \$overrides): array
            {
                foreach (\$overrides as \$key => \$value) {
                    \$existing = \$base[\$key] ?? null;

                    \$base[\$key] = is_array(\$value) && is_array(\$existing)
                        && ! array_is_list(\$value) && ! array_is_list(\$existing)
                            ? self::merge(\$existing, \$value)
                            : \$value;
                }

                return \$base;
            }

            /**
             * @return array<string, array<string, mixed>>
             */
            public static function skeletons(): array
            {
                return [
        {$body}
                ];
            }
        }

        PHP;

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, $contents);
    }

    /**
     * Builds the smallest payload the schema will accept. Required-but-nullable fields become
     * null, which keeps fixtures shallow; only required non-nullable objects get inlined.
     *
     * @param  list<string>  $stack  guards against schemas that reference themselves
     * @return array<string, mixed>|null
     */
    private function fixtureFor(array $schema, array $stack): mixed
    {
        if (isset($schema['$ref'])) {
            $name = $this->refName($schema['$ref']);

            if (in_array($name, $stack, true)) {
                return null;
            }

            $target = $this->schemas[$name] ?? null;
            if ($target === null) {
                return null;
            }

            return $this->fixtureFor($target, [...$stack, $name]);
        }

        if (isset($schema['const'])) {
            return $schema['const'];
        }

        if (isset($schema['enum'])) {
            return $schema['enum'][0];
        }

        if (isset($schema['oneOf']) || isset($schema['anyOf'])) {
            $members = $schema['oneOf'] ?? $schema['anyOf'];

            foreach ($members as $member) {
                if (($member['type'] ?? null) === 'null') {
                    return null;
                }
            }

            return $this->fixtureFor($members[0], $stack);
        }

        $type = $schema['type'] ?? null;

        if ($type === 'object' || isset($schema['properties'])) {
            $required = $schema['required'] ?? [];
            $out = [];

            foreach ($schema['properties'] ?? [] as $propName => $propSchema) {
                if (! in_array($propName, $required, true)) {
                    continue;
                }

                $out[$propName] = $this->fixtureFor($propSchema, $stack);
            }

            return $out;
        }

        return match ($type) {
            'string' => match ($schema['format'] ?? null) {
                'uuid4', 'uuid' => '00000000-0000-0000-0000-000000000000',
                'date-time' => '2026-01-01T00:00:00Z',
                'date' => '2026-01-01',
                'email' => 'customer@example.com',
                'uri' => 'https://example.com',
                default => 'placeholder',
            },
            'integer' => 0,
            'number' => 0.0,
            'boolean' => false,
            'array' => [],
            default => null,
        };
    }

    private function exportArray(mixed $value, int $depth): string
    {
        $pad = str_repeat('    ', $depth);
        $innerPad = str_repeat('    ', $depth + 1);

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return var_export($value, true);
        }

        if (is_string($value)) {
            return "'" . addcslashes($value, "'\\") . "'";
        }

        if ($value === []) {
            return '[]';
        }

        $lines = [];
        $isList = array_is_list($value);

        foreach ($value as $key => $item) {
            $rendered = $this->exportArray($item, $depth + 1);
            $lines[] = $isList
                ? "{$innerPad}{$rendered},"
                : "{$innerPad}'" . addcslashes((string) $key, "'\\") . "' => {$rendered},";
        }

        return "[\n" . implode("\n", $lines) . "\n{$pad}]";
    }

    /**
     * Lift the inline enums named in INLINE_ENUMS into real schemas and point their owning
     * properties at them, so the rest of the generator sees an ordinary $ref.
     */
    private function promoteInlineEnums(): void
    {
        foreach (INLINE_ENUMS as $location => $enumName) {
            [$schemaName, $propName] = explode('.', $location, 2);

            $propSchema = $this->schemas[$schemaName]['properties'][$propName] ?? null;

            if ($propSchema === null || ! isset($propSchema['enum'])) {
                $this->warnings[] = "inline enum {$location} not found or not an enum";

                continue;
            }

            $this->schemas[$enumName] ??= [
                'type' => $propSchema['type'] ?? 'string',
                'enum' => $propSchema['enum'],
            ];

            $this->schemas[$schemaName]['properties'][$propName] = [
                '$ref' => "#/components/schemas/{$enumName}",
            ] + array_diff_key($propSchema, ['enum' => null, 'type' => null]);

            $this->closure[$enumName] = true;
        }
    }

    // -- collection ---------------------------------------------------------

    private function collect(string $name): void
    {
        if (isset($this->closure[$name])) {
            return;
        }

        if (! isset($this->schemas[$name])) {
            $this->warnings[] = "unknown schema {$name}";

            return;
        }

        $this->closure[$name] = true;

        foreach ($this->refsIn($this->schemas[$name]) as $ref) {
            $this->collect($ref);
        }
    }

    /** @return list<string> */
    private function refsIn(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $found = [];

        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_contains($value, '/schemas/')) {
                $found[] = $this->refName($value);

                continue;
            }

            $found = [...$found, ...$this->refsIn($value)];
        }

        return $found;
    }

    private function refName(string $ref): string
    {
        return substr($ref, strrpos($ref, '/') + 1);
    }

    // -- rendering ----------------------------------------------------------

    private function renderEnum(string $class, string $name, array $schema): string
    {
        $backing = ($schema['type'] ?? 'string') === 'integer' ? 'int' : 'string';

        $cases = [];
        foreach ($schema['enum'] as $value) {
            $caseName = $this->caseName((string) $value);
            $literal = $backing === 'int' ? (string) $value : "'" . addcslashes((string) $value, "'\\") . "'";
            $cases[] = "    case {$caseName} = {$literal};";
        }

        $body = implode("\n", $cases);

        return <<<PHP
        <?php

        // This file is generated by tools/generate-data.php. Do not edit by hand.

        declare(strict_types=1);

        namespace {$this->enumNamespace()};

        enum {$class}: {$backing}
        {
        {$body}
        }

        PHP;
    }

    private function renderUnion(string $class, string $name, array $schema): string
    {
        $members = array_map(
            fn(array $m) => $this->refName($m['$ref']),
            array_filter($schema['oneOf'] ?? $schema['anyOf'] ?? [], fn($m) => isset($m['$ref'])),
        );
        $members = array_values($members);

        $override = UNION_OVERRIDES[$name] ?? null;
        $discriminators = $override !== null || $this->isRequestUnion($name)
            ? null
            : $this->discriminatorsFor($schema, $members);

        if ($discriminators === null && $override === null) {
            // Request-side unions are built by the caller, never parsed, so a marker interface
            // is all they need: it lets the client accept `A|B|C` without inventing a shape.
            $this->warnings[] = "union {$name} has no discriminator; emitting marker interface";

            return $this->renderMarkerInterface($class, $name, $members);
        }

        // Members of a morphable union extend the abstract base, so their shared properties
        // live on the base and the base owns the morph.
        $shared = $this->sharedProperties($members);

        $uses = [
            'Danestves\\LaravelPolar\\Support\\PolarData',
            'Spatie\\LaravelData\\Attributes\\MapName',
            'Spatie\\LaravelData\\Mappers\\SnakeCaseMapper',
        ];

        if ($override !== null) {
            $keys = $override['morphKeys'];
            $optionalKeys = $override['morphOptional'] ?? [];
            $morphBody = $override['morphBody'] ?? null;
            $factory = $override['factory'] ?? null;
            $uses = [...$uses, ...$override['uses']];
        } else {
            [$keys, $mapping] = $discriminators;
            $optionalKeys = [];
            $factory = null;

            $arms = [];
            foreach ($mapping as $signature => $member) {
                $memberClass = $this->className($member);
                $arms[] = "            {$signature} => {$memberClass}::class,";
            }
            $armsBody = implode("\n", $arms);

            $subject = count($keys) === 1
                ? "\$properties['" . $this->camel($keys[0]) . "']"
                : 'json_encode([' . implode(', ', array_map(
                    fn($k) => "\$properties['" . $this->camel($k) . "']",
                    $keys,
                )) . '])';

            $morphBody = "        return match ({$subject}) {\n{$armsBody}\n            default => null,\n        };";
        }

        if ($keys !== []) {
            $uses[] = 'Spatie\\LaravelData\\Attributes\\PropertyForMorph';
        }

        if ($morphBody !== null) {
            $uses[] = 'Spatie\\LaravelData\\Contracts\\PropertyMorphableData';
        }

        $shared = $this->withMorphKeys($shared, $keys, $members);

        $props = [];
        foreach ($shared as $propName => $propSchema) {
            $isMorphKey = in_array($propName, $keys, true);
            $props[] = $this->renderProperty(
                $propName,
                $propSchema,
                // A morph key some payloads legitimately omit has to be nullable with a default:
                // laravel-data bails out of morphing entirely when a morph key has neither.
                required: ! in_array($propName, $optionalKeys, true),
                uses: $uses,
                attributes: $isMorphKey ? ['#[PropertyForMorph]'] : [],
                promoted: false,
                // A morph key must stay a plain scalar/enum: morph() runs before casting.
                forceMorphScalar: $isMorphKey,
            );
        }

        $methods = [];
        if ($morphBody !== null) {
            $methods[] = "    public static function morph(array \$properties): ?string\n    {\n{$morphBody}\n    }";
        }
        if ($factory !== null) {
            $methods[] = $factory;
        }

        $implements = $morphBody !== null ? ' implements PropertyMorphableData' : '';
        $propsBody = implode("\n\n", $props);
        $methodsBody = $methods === [] ? '' : "\n\n" . implode("\n\n", $methods);
        $usesBody = $this->renderUses($uses, $this->dataNamespace());
        $doc = $this->classDoc($schema);

        return <<<PHP
        <?php

        // This file is generated by tools/generate-data.php. Do not edit by hand.

        declare(strict_types=1);

        namespace {$this->dataNamespace()};

        {$usesBody}
        {$doc}#[MapName(SnakeCaseMapper::class)]
        abstract class {$class} extends PolarData{$implements}
        {
        {$propsBody}{$methodsBody}
        }

        PHP;
    }

    private function renderMarkerInterface(string $class, string $name, array $members): string
    {
        $doc = "/**\n * Union of: " . implode(', ', array_map([$this, 'className'], $members)) . "\n */\n";

        return <<<PHP
        <?php

        // This file is generated by tools/generate-data.php. Do not edit by hand.

        declare(strict_types=1);

        namespace {$this->dataNamespace()};

        {$doc}interface {$class}
        {
        }

        PHP;
    }

    private function renderObject(string $class, string $name, array $schema, ?string $extends): string
    {
        $uses = [
            'Spatie\\LaravelData\\Attributes\\MapName',
            'Spatie\\LaravelData\\Mappers\\SnakeCaseMapper',
        ];

        $parent = $this->parentUnionOf($name);
        $skip = [];

        if ($parent !== null) {
            $parentSchema = $this->schemas[$parent];
            $parentMembers = array_values(array_map(
                fn(array $m) => $this->refName($m['$ref']),
                array_filter($parentSchema['oneOf'] ?? $parentSchema['anyOf'] ?? [], fn($m) => isset($m['$ref'])),
            ));
            // Whatever the base declares, the subclass must not redeclare — including a morph
            // key the base had to adopt even though it wasn't shared, since re-emitting it with
            // the member's own (often optional) type would clash with the base's.
            $skip = array_keys($this->baseProperties($parent, $parentMembers));
            $extends = $this->className($parent);
        } else {
            $uses[] = 'Danestves\\LaravelPolar\\Support\\PolarData';
            $extends = 'PolarData';
        }

        $implements = '';
        $marker = $this->markerUnionOf($name);
        if ($marker !== null) {
            $implements = ' implements ' . $this->className($marker);
        }

        $required = $schema['required'] ?? [];

        // Optional properties get a `= null` default, so they must follow the required ones or
        // PHP raises "optional parameter declared before required parameter". The spec's own
        // ordering makes no such promise, so sort here (stably, to keep the spec's order within
        // each group).
        $ordered = [];
        foreach ([true, false] as $wantRequired) {
            foreach ($schema['properties'] ?? [] as $propName => $propSchema) {
                if (in_array($propName, $skip, true)) {
                    continue;
                }

                $isRequired = in_array($propName, $required, true) && ! isset($propSchema['const']);

                if ($isRequired === $wantRequired) {
                    $ordered[$propName] = $propSchema;
                }
            }
        }

        $props = [];
        foreach ($ordered as $propName => $propSchema) {
            $props[] = $this->renderProperty(
                $propName,
                $propSchema,
                required: in_array($propName, $required, true),
                uses: $uses,
                promoted: $parent === null,
            );
        }

        $usesBody = $this->renderUses($uses, $this->dataNamespace());
        $doc = $this->classDoc($schema);

        if ($props === []) {
            $body = '';
        } elseif ($parent === null) {
            // A standalone data object gets a promoted constructor; laravel-data uses it to
            // hydrate, and it keeps the object immutable.
            $body = "    public function __construct(\n" . implode("\n", $props) . "\n    ) {}\n";
        } else {
            $body = implode("\n\n", $props) . "\n";
        }

        return <<<PHP
        <?php

        // This file is generated by tools/generate-data.php. Do not edit by hand.

        declare(strict_types=1);

        namespace {$this->dataNamespace()};

        {$usesBody}
        {$doc}#[MapName(SnakeCaseMapper::class)]
        class {$class} extends {$extends}{$implements}
        {
        {$body}}

        PHP;
    }

    /**
     * Renders a single property, either as a promoted constructor parameter or a plain
     * class property (union members cannot promote, since the base owns the shared ones).
     *
     * @param  list<string>  $uses  collected by reference so the caller can emit imports
     * @param  list<string>  $attributes
     */
    private function renderProperty(
        string $name,
        array $schema,
        bool $required,
        array &$uses,
        array $attributes = [],
        bool $promoted = true,
        bool $forceMorphScalar = false,
    ): string {
        $camel = $this->camel($name);
        [$type, $docType] = $this->phpType($schema, $uses, $forceMorphScalar);

        $nullable = (! $required || $this->isNullable($schema)) && ! isset($schema['const']);
        if ($nullable && $type !== 'mixed' && ! str_starts_with($type, '?') && ! str_contains($type, '|')) {
            $type = '?' . $type;
        } elseif ($nullable && str_contains($type, '|') && ! str_contains($type, 'null')) {
            $type .= '|null';
        }

        // A `const` is the schema's own discriminator value: default it so callers building a
        // request never have to restate `type: 'text'` on a class that can only be text.
        $default = match (true) {
            isset($schema['const']) && is_string($schema['const']) => " = '" . addcslashes($schema['const'], "'\\") . "'",
            isset($schema['const']) && is_bool($schema['const']) => ' = ' . ($schema['const'] ? 'true' : 'false'),
            $required => '',
            default => ' = null',
        };
        $indent = $promoted ? '        ' : '    ';

        $lines = [];

        $description = $schema['description'] ?? null;
        if ($description !== null || $docType !== null) {
            $doc = [];
            if ($docType !== null) {
                $doc[] = ($nullable ? "{$docType}|null" : $docType);
            }
            if ($doc !== [] || $description !== null) {
                $lines[] = $indent . '/**';
                if ($description !== null) {
                    foreach (explode("\n", wordwrap(trim($description), 92)) as $line) {
                        $lines[] = rtrim($indent . ' * ' . $line);
                    }
                }
                if ($docType !== null) {
                    if ($description !== null) {
                        $lines[] = $indent . ' *';
                    }
                    $lines[] = $indent . ' * @var ' . ($nullable ? "{$docType}|null" : $docType);
                }
                $lines[] = $indent . ' */';
            }
        }

        foreach ($attributes as $attribute) {
            $lines[] = $indent . $attribute;
        }

        // Union members declare plain properties (the abstract base owns the constructor), so an
        // optional one still needs its default or it stays uninitialised when Polar omits it.
        $lines[] = $promoted
            ? "{$indent}public readonly {$type} \${$camel}{$default},"
            : "{$indent}public {$type} \${$camel}{$default};";

        return implode("\n", $lines);
    }

    /**
     * Maps an OpenAPI schema node onto a PHP type plus an optional richer docblock type
     * (used for arrays, which PHP cannot express).
     *
     * @param  list<string>  $uses
     * @return array{0: string, 1: ?string}
     */
    private function phpType(array $schema, array &$uses, bool $forceMorphScalar = false): array
    {
        if (isset($schema['$ref'])) {
            $target = $this->refName($schema['$ref']);
            $targetSchema = $this->schemas[$target] ?? null;

            if ($targetSchema === null) {
                return ['mixed', null];
            }

            if ($this->isEnum($targetSchema)) {
                $class = $this->className($target);
                $uses[] = $this->enumNamespace() . '\\' . $class;

                return [$class, null];
            }

            if ($this->isPassthroughObject($targetSchema)) {
                return ['array', 'array<string, mixed>'];
            }

            $class = $this->className($target);

            return [$class, null];
        }

        // `const` on a discriminator: a plain string.
        if (isset($schema['const'])) {
            return [is_bool($schema['const']) ? 'bool' : 'string', null];
        }

        if (isset($schema['anyOf']) || isset($schema['oneOf'])) {
            $members = $schema['anyOf'] ?? $schema['oneOf'];
            $nonNull = array_values(array_filter($members, fn($m) => ($m['type'] ?? null) !== 'null'));

            if (count($nonNull) === 1) {
                return $this->phpType($nonNull[0], $uses, $forceMorphScalar);
            }

            $types = [];
            $docTypes = [];
            foreach ($nonNull as $member) {
                [$t, $d] = $this->phpType($member, $uses, $forceMorphScalar);
                $types[] = $t;
                $docTypes[] = $d ?? $t;
            }

            $types = array_values(array_unique($types));
            $docTypes = array_values(array_unique($docTypes));

            // PHP cannot union `array` with itself or express list<string>|string precisely,
            // so collapse to the docblock when the PHP types would be ambiguous.
            if (count($types) === 1) {
                return [$types[0], count($docTypes) === 1 ? $docTypes[0] : implode('|', $docTypes)];
            }

            return [implode('|', $types), implode('|', $docTypes)];
        }

        $type = $schema['type'] ?? null;

        if (is_array($type)) {
            $type = array_values(array_filter($type, fn($t) => $t !== 'null'))[0] ?? null;
        }

        return match ($type) {
            'string' => ($schema['format'] ?? null) === 'date-time' && ! $forceMorphScalar
                ? $this->carbon($uses)
                : ['string', null],
            'integer' => ['int', null],
            'number' => ['float', null],
            'boolean' => ['bool', null],
            'array' => $this->arrayType($schema, $uses),
            'object' => ['array', 'array<string, mixed>'],
            default => ['mixed', null],
        };
    }

    /** @param list<string> $uses */
    private function carbon(array &$uses): array
    {
        $uses[] = 'Carbon\\CarbonImmutable';

        return ['CarbonImmutable', null];
    }

    /** @param list<string> $uses */
    private function arrayType(array $schema, array &$uses): array
    {
        $items = $schema['items'] ?? null;

        if ($items === null) {
            return ['array', 'list<mixed>'];
        }

        [$itemType, $itemDoc] = $this->phpType($items, $uses);

        return ['array', 'list<' . ($itemDoc ?? $itemType) . '>'];
    }

    // -- union analysis -----------------------------------------------------

    /**
     * Works out which property (or pair of properties) tells the union members apart.
     * Prefers the spec's own discriminator, then falls back to scanning for `const` fields
     * whose values are unique across members.
     *
     * @param  list<string>  $members
     * @return array{0: list<string>, 1: array<string, string>}|null
     */
    private function discriminatorsFor(array $schema, array $members): ?array
    {
        if (isset($schema['discriminator']['mapping'])) {
            $key = $schema['discriminator']['propertyName'];
            $mapping = [];
            foreach ($schema['discriminator']['mapping'] as $value => $ref) {
                $mapping["'" . addcslashes((string) $value, "'\\") . "'"] = $this->refName($ref);
            }

            return [[$key], $mapping];
        }

        // Single-key const scan.
        $candidates = $this->constKeys($members);
        foreach ($candidates as $key => $valuesByMember) {
            if (count(array_unique($valuesByMember)) === count($members)) {
                $mapping = [];
                foreach ($valuesByMember as $member => $value) {
                    $mapping["'" . addcslashes((string) $value, "'\\") . "'"] = $member;
                }

                return [[$key], $mapping];
            }
        }

        // Two-key composite scan (e.g. Discount is keyed on type + duration).
        $keys = array_keys($candidates);
        foreach ($keys as $i => $a) {
            foreach (array_slice($keys, $i + 1) as $b) {
                $signatures = [];
                foreach ($members as $member) {
                    $signatures[$member] = json_encode([$candidates[$a][$member] ?? null, $candidates[$b][$member] ?? null]);
                }

                if (count(array_unique($signatures)) === count($members)) {
                    $mapping = [];
                    foreach ($signatures as $member => $signature) {
                        $mapping["'" . addcslashes($signature, "'\\") . "'"] = $member;
                    }

                    return [[$a, $b], $mapping];
                }
            }
        }

        return null;
    }

    /**
     * Collects `const`-valued properties shared by every union member.
     *
     * @param  list<string>  $members
     * @return array<string, array<string, string>>  key => member => const value
     */
    private function constKeys(array $members): array
    {
        $candidates = [];

        foreach ($members as $member) {
            foreach ($this->schemas[$member]['properties'] ?? [] as $name => $propSchema) {
                if (isset($propSchema['const']) && ! is_bool($propSchema['const'])) {
                    $candidates[$name][$member] = (string) $propSchema['const'];
                }
            }
        }

        return array_filter($candidates, fn(array $byMember) => count($byMember) === count($members));
    }

    /**
     * Properties every member of a union declares identically — these move onto the
     * abstract base so subclasses only carry what actually differs.
     *
     * @param  list<string>  $members
     * @return array<string, array<string, mixed>>
     */
    private function sharedProperties(array $members): array
    {
        if ($members === []) {
            return [];
        }

        $first = $this->schemas[$members[0]]['properties'] ?? [];
        $requiredEverywhere = array_intersect(...array_map(
            fn(string $m) => $this->schemas[$m]['required'] ?? [],
            $members,
        ));

        $shared = [];

        foreach ($first as $name => $schema) {
            if (! in_array($name, $requiredEverywhere, true)) {
                continue;
            }

            foreach ($members as $member) {
                $other = $this->schemas[$member]['properties'][$name] ?? null;
                if ($other === null) {
                    continue 2;
                }
                // Discriminator consts differ per member; keep them as a plain string on the base.
                if (isset($schema['const']) !== isset($other['const'])) {
                    continue 2;
                }
                if (! isset($schema['const']) && $other !== $schema) {
                    continue 2;
                }
            }

            $shared[$name] = isset($schema['const']) ? ['type' => 'string'] : $schema;
        }

        return $shared;
    }

    /**
     * The full property set a union's abstract base declares: everything the members share,
     * plus any discriminator the base needs for morph() even though it isn't shared.
     *
     * @param  list<string>  $members
     * @return array<string, array<string, mixed>>
     */
    private function baseProperties(string $union, array $members): array
    {
        $shared = $this->sharedProperties($members);
        $keys = isset(UNION_OVERRIDES[$union])
            ? UNION_OVERRIDES[$union]['morphKeys']
            : ($this->discriminatorsFor($this->schemas[$union], $members)[0] ?? []);

        return $this->withMorphKeys($shared, $keys, $members);
    }

    /**
     * A discriminator that only some members pin (Polar's payment methods declare
     * `type: "card"` on the card variant and leave it open on the generic one) never counts as
     * shared, but morph() cannot run without it — so add it back as a plain string.
     *
     * @param  array<string, array<string, mixed>>  $shared
     * @param  list<string>  $keys
     * @param  list<string>  $members
     * @return array<string, array<string, mixed>>
     */
    private function withMorphKeys(array $shared, array $keys, array $members): array
    {
        foreach ($keys as $key) {
            if (isset($shared[$key])) {
                continue;
            }

            $schema = $this->schemas[$members[0]]['properties'][$key] ?? [];
            $shared[$key] = ['type' => 'string'] + array_diff_key($schema, ['const' => null, 'enum' => null, 'type' => null]);
        }

        return $shared;
    }

    private function parentUnionOf(string $name): ?string
    {
        foreach (array_keys($this->closure) as $candidate) {
            $schema = $this->schemas[$candidate];
            if (! $this->isUnion($schema)) {
                continue;
            }

            $members = array_map(
                fn(array $m) => $this->refName($m['$ref']),
                array_filter($schema['oneOf'] ?? $schema['anyOf'] ?? [], fn($m) => isset($m['$ref'])),
            );

            if (! in_array($name, $members, true)) {
                continue;
            }

            if ($this->isRequestUnion($candidate)) {
                continue;
            }

            if (isset(UNION_OVERRIDES[$candidate]) || $this->discriminatorsFor($schema, array_values($members)) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function markerUnionOf(string $name): ?string
    {
        foreach (array_keys($this->closure) as $candidate) {
            $schema = $this->schemas[$candidate];
            if (! $this->isUnion($schema)) {
                continue;
            }

            $members = array_values(array_map(
                fn(array $m) => $this->refName($m['$ref']),
                array_filter($schema['oneOf'] ?? $schema['anyOf'] ?? [], fn($m) => isset($m['$ref'])),
            ));

            if (isset(UNION_OVERRIDES[$candidate])) {
                continue;
            }

            if (! in_array($name, $members, true)) {
                continue;
            }

            if ($this->isRequestUnion($candidate) || $this->discriminatorsFor($schema, $members) === null) {
                return $candidate;
            }
        }

        return null;
    }

    // -- helpers ------------------------------------------------------------

    private function isEnum(array $schema): bool
    {
        return isset($schema['enum']);
    }

    private function isUnion(array $schema): bool
    {
        return (isset($schema['oneOf']) || isset($schema['anyOf'])) && ! isset($schema['properties']);
    }

    /**
     * Whether a union is something the caller builds rather than something Polar sends back.
     *
     * Response unions become an abstract base that morph() resolves, which forces their members
     * to use plain properties. Request unions have no such need — and members with a promoted
     * constructor can be built with named arguments — so they get a marker interface instead.
     */
    private function isRequestUnion(string $name): bool
    {
        return str_ends_with($name, 'Create') || str_ends_with($name, 'Update');
    }

    /**
     * Whether a schema is an open map rather than a fixed shape.
     *
     * Objects with no declared properties are obviously free-form, but so are objects that
     * declare `additionalProperties` — Polar uses those for metadata bags where the named keys
     * (`_cost`, `_llm`) sit alongside arbitrary user-supplied ones. Closing those into a class
     * would silently drop everything the caller put in.
     */
    private function isPassthroughObject(array $schema): bool
    {
        if (($schema['type'] ?? null) !== 'object') {
            return false;
        }

        return ! isset($schema['properties'])
            || ($schema['additionalProperties'] ?? false) !== false;
    }

    private function classDoc(array $schema): string
    {
        $description = $schema['description'] ?? null;

        if ($description === null) {
            return '';
        }

        $lines = ["/**"];
        foreach (explode("\n", wordwrap(trim($description), 96)) as $line) {
            $lines[] = rtrim(' * ' . $line);
        }
        $lines[] = ' */';

        return implode("\n", $lines) . "\n";
    }

    /** @param list<string> $uses */
    private function renderUses(array $uses, string $currentNamespace): string
    {
        $uses = array_values(array_unique(array_filter(
            $uses,
            fn(string $use) => substr($use, 0, strrpos($use, '\\')) !== $currentNamespace,
        )));
        sort($uses);

        return implode("\n", array_map(fn(string $use) => "use {$use};", $uses)) . "\n";
    }

    private function className(string $name): string
    {
        // ListResource[X] / CostMetadata-Input style names need sanitising.
        $name = str_replace(['-', '_', '[', ']'], ' ', $name);
        $name = str_replace(' ', '', ucwords($name));

        return $name;
    }

    private function camel(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    }

    private function caseName(string $value): string
    {
        // Polar's sort enums pair each field with a `-` prefixed descending variant. Stripping
        // the sign would collide the two, so it becomes a `Desc` suffix instead.
        $descending = str_starts_with($value, '-');
        $value = $descending ? substr($value, 1) : $value;

        $name = str_replace(' ', '', ucwords(str_replace(['_', '-', '.'], ' ', $value)));

        if ($descending) {
            $name .= 'Desc';
        }

        if ($name === '' || is_numeric($name[0])) {
            $name = 'Value' . $name;
        }

        return $name;
    }

    private function isNullable(array $schema): bool
    {
        if (isset($schema['anyOf']) || isset($schema['oneOf'])) {
            foreach ($schema['anyOf'] ?? $schema['oneOf'] as $member) {
                if (($member['type'] ?? null) === 'null') {
                    return true;
                }
            }
        }

        $type = $schema['type'] ?? null;

        return is_array($type) && in_array('null', $type, true);
    }

    private function dataNamespace(): string
    {
        return DATA_NS;
    }

    private function enumNamespace(): string
    {
        return ENUM_NS;
    }

    private function resetDir(string $dir): void
    {
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                unlink($file);
            }
        } else {
            mkdir($dir, 0o755, true);
        }
    }
}
