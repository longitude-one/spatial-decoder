# Strategy configuration

The strategy configuration contract is independent of input and execution interfaces.
`Strategy\ConfigurableStrategyInterface` declares `configureOptions(OptionsResolver $resolver): void`.
`Strategy\AbstractConfigurableStrategy` optionally resolves `array $options = []` at construction and retains only a protected readonly `$options` array.
The resolver is not retained. Override `configureOptions()` to declare defaults, allowed types, allowed values and normalizers.
Unknown or invalid options fail at construction with Symfony OptionsResolver exceptions.

## Configure WKT, EWKT and WKB output

All three strategies accept the same options at construction:

| Option | Default | Accepted values |
| --- | --- | --- |
| `mode` | `SpatialModelEnum::GEOMETRY` | `SpatialModelEnum::GEOMETRY` or `SpatialModelEnum::GEOGRAPHY` |
| `mutable` | `false` | `bool` |

```php
use LongitudeOne\Core\Enum\SpatialModelEnum;
use LongitudeOne\SpatialDecoder\Decoder;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;

$decoder = new Decoder(new EwktDecoderStrategy([
    'mode' => SpatialModelEnum::GEOGRAPHY,
    'mutable' => true,
]));
$point = $decoder->decode('SRID=4326;POINT (1 2)');
```

The choice applies to the complete decoded graph, including collection members, polygon rings and their points.
Supported coordinates, dimensions, geometry types, EMPTY values and declared spatial references are preserved.
WKB currently supports only non-empty XY Points; see [WKB decoding](decoding.md#wkb-decoding).
Selecting Geography changes the object's family; it does not project coordinates or establish geometric validity.
WKT, WKB and unprefixed EWKT retain SRID 0. EWKT prefixes still apply to the complete graph.
Configuration is local to each strategy and never changes the shared spatial factory mode.

Strings such as `'Geography'`, numeric mutable flags and unknown options are rejected at construction.
Use the enum case and a boolean. No `srid` option is defined on these built-in strategies.

## Custom decoding without inheritance

This application-specific strategy declares a spatial reference without transforming coordinates:

```php
use LongitudeOne\SpatialDecoder\Strategy\ConfigurableStrategyInterface;
use LongitudeOne\SpatialDecoder\Strategy\EwktDecoderStrategy;
use LongitudeOne\SpatialDecoder\Strategy\StringDecoderStrategyInterface;
use LongitudeOne\SpatialTypes\Interfaces\SpatialInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** An application-owned decoding strategy without base-class inheritance. */
final class ExternalStrategy implements ConfigurableStrategyInterface, StringDecoderStrategyInterface
{
    /** @var array<string, mixed> Resolved options. */
    private readonly array $options;

    /** @param array<string, mixed> $options application configuration */
    public function __construct(array $options = [])
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);
    }

    /** @param OptionsResolver $resolver local configuration resolver */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('srid', 0);
        $resolver->setAllowedTypes('srid', 'int');
        $resolver->setAllowedValues('srid', static fn (int $value): bool => $value >= 0);
    }

    /** @param string $data input WKT with an application-declared reference */
    public function decode(string $data): SpatialInterface
    {
        return new EwktDecoderStrategy()->decode(\sprintf('SRID=%d;%s', $this->options['srid'], $data));
    }
}
```

```php
$decoder = new \LongitudeOne\SpatialDecoder\Decoder(new ExternalStrategy(['srid' => 4326]));
$point = $decoder->decode('POINT (1 2)');
```

The orchestrator still accepts strategies implementing only the existing execution interfaces.
No base-class inheritance or orchestrator modification is required for custom options.

## Compatibility

OptionsResolver 5.4, 6, 7 and 8 are supported. Only APIs available in 5.4 are used.
No Symfony dependency is added to spatial-core.
The concrete `WktDecoderStrategy::decode()` and `EwktDecoderStrategy::decode()` methods now return `SpatialInterface`,
rather than promising `ImmutableSpatialInterface` for every configuration. Their default output remains immutable.
Code requiring immutable copy methods should check `ImmutableSpatialInterface` before using them; mutable output uses the corresponding mutation interfaces.
