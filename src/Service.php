<?php declare(strict_types=1);

namespace Kiboko\Plugin\FastMap;

use Kiboko\Contract\Configurator\ConfiguratorPackagesInterface;
use Kiboko\Contract\Configurator\ConfiguratorTransformerInterface;
use Kiboko\Contract\Configurator\RepositoryInterface;
use Kiboko\Plugin\FastMap\Factory;
use Kiboko\Contract\Configurator\InvalidConfigurationException;
use Kiboko\Contract\Configurator\ConfigurationExceptionInterface;
use Kiboko\Contract\Configurator\FactoryInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception as Symfony;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class Service implements FactoryInterface, ConfiguratorTransformerInterface, ConfiguratorPackagesInterface
{
    private Processor $processor;
    private ConfigurationInterface $configuration;
    private ExpressionLanguage $interpreter;

    public function __construct(
        ?ExpressionLanguage $interpreter = null,
        private array $additionalExpressionVariables = []
    ) {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
        $this->interpreter = $interpreter ?? new ExpressionLanguage();
    }

    public function configuration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    /**
     * @throws ConfigurationExceptionInterface
     */
    public function normalize(array $config): array
    {
        try {
            return $this->processor->processConfiguration($this->configuration, $config);
        } catch (Symfony\InvalidTypeException|Symfony\InvalidConfigurationException $exception) {
            throw new InvalidConfigurationException($exception->getMessage(), 0, $exception);
        }
    }

    public function validate(array $config): bool
    {
        try {
            $this->processor->processConfiguration($this->configuration, $config);

            return true;
        } catch (Symfony\InvalidTypeException|Symfony\InvalidConfigurationException $exception) {
            return false;
        }
    }

    /**
     * @throws ConfigurationExceptionInterface
     */
    public function compile(array $config): RepositoryInterface
    {
        if (array_key_exists('expression_language', $config)
            && is_array($config['expression_language'])
            && count($config['expression_language'])
        ) {
            foreach ($config['expression_language'] as $provider) {
                $this->interpreter->registerProvider(new $provider);
            }
        }

        try {
            if (array_key_exists('conditional', $config)) {
                $conditionalFactory = new Factory\ConditionalMapper($this->interpreter, $this->additionalExpressionVariables);

                return $conditionalFactory->compile($config['conditional']);
            } elseif (array_key_exists('map', $config)) {
                $arrayFactory = new Factory\ArrayMapper($this->interpreter, $this->additionalExpressionVariables);

                return $arrayFactory->compile($config['map']);
            } elseif (array_key_exists('object', $config)) {
                $objectFactory = new Factory\ObjectMapper($this->interpreter, $this->additionalExpressionVariables);

                return $objectFactory->compile($config['object']);
            } else {
                throw new InvalidConfigurationException(
                    'Could not determine if the factory should build an array or an object transformer.'
                );
            }
        } catch (Symfony\InvalidTypeException|Symfony\InvalidConfigurationException $exception) {
            throw new InvalidConfigurationException($exception->getMessage(), 0, $exception);
        }
    }

    public function getPackages(): array
    {
        return [
            'php-etl/fast-map:^0.2.0',
        ];
    }

    public function getTransformerKeys(): ?array
    {
        return null;
    }
}
