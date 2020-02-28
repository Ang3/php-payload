<?php

namespace Ang3\Component\Http;

use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;

/**
 * @author Joanis ROUANET
 */
class Payload implements IteratorAggregate
{
    /**
     * List of encoding formats.
     */
    const JSON_FORMAT = 'json';
    const XML_FORMAT = 'xml';
    const YAML_FORMAT = 'yaml';
    const CSV_FORMAT = 'csv';

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var object|iterable
     */
    private $data;

    /**
     * @var Serializer|null
     */
    private static $serializer;

    /**
     * @internal
     *
     * @param mixed $data
     */
    protected function __construct($data)
    {
        // Hydratation
        $this->propertyAccessor = new PropertyAccessor();
        $this->setData($data);
    }

    /**
     * @param mixed|null $value
     *
     * @return mixed|null
     */
    public function __set(string $name, $value = null)
    {
        return $this->set($name, $value);
    }

    /**
     * @return mixed|null
     */
    public function __get(string $name)
    {
        return $this->get($name);
    }

    /**
     * Create a payload from $_GET params.
     *
     * @static
     */
    public static function createFromUrl(): self
    {
        return new self($_GET);
    }

    /**
     * @static
     *
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When decoding failed
     */
    public static function parseResponse(ResponseInterface $response, string $format, array $context = []): self
    {
        // Récupération du contenu de la réponse
        $contents = $response
            ->getBody()
            ->getContents()
        ;

        // Retour du parsing des données selon le format et le contexte
        return self::parse($contents, $format, $context);
    }

    /**
     * @static
     *
     * @param mixed $json
     *
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When decoding failed
     */
    public static function parseJson($json, array $context = []): self
    {
        return self::parse($json, self::JSON_FORMAT, $context);
    }

    /**
     * @static
     *
     * @param mixed $xml
     *
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When decoding failed
     */
    public static function parseXml($xml, array $context = []): self
    {
        return self::parse($xml, self::XML_FORMAT, $context);
    }

    /**
     * @static
     *
     * @param mixed $yaml
     *
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When decoding failed
     */
    public static function parseYaml($yaml, array $context = []): self
    {
        return self::parse($yaml, self::YAML_FORMAT, $context);
    }

    /**
     * @static
     *
     * @param mixed $csv
     *
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When decoding failed
     */
    public static function parseCsv($csv, array $context = []): self
    {
        return self::parse($csv, self::CSV_FORMAT, $context);
    }

    /**
     * @static
     *
     * @param mixed $data
     *
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When decoding failed
     */
    public static function parse($data, string $format, array $context = []): self
    {
        if (!self::getSerializer()->supportsEncoding($format, $context)) {
            throw new InvalidArgumentException(sprintf('The format "%s" is not supported', $format));
        }

        try {
            return self::create(self::getSerializer()->decode($data, $format, $context));
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to parse content with format "%s" - %s', $format, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @static
     *
     * @param mixed $data
     */
    public static function create($data): self
    {
        return new self($data);
    }

    /**
     * @static
     *
     * @param mixed $data
     */
    public static function supports($data): bool
    {
        return is_object($data) || is_iterable($data);
    }

    /**
     * {@inheritdoc}.
     */
    public function getIterator()
    {
        yield from $this->discover();
    }

    public function discover(array $options = []): array
    {
        // Récupération des données à analyser
        $data = $this->data;

        // Si on a un chemin spécifique en option
        if (!empty($options['path'])) {
            // Récupération de la valeur sur le chemin en option
            $data = $this->get($options['path']);

            // Réinitialisation du chemin en option
            $options['path'] = null;
        }

        // Définition des options internes
        $options['excluded_classes'] = [];

        // Retour de la découverte des données
        return $this->discoverData($data, $options);
    }

    public function isEmpty(): bool
    {
        // Récupération des propriétés racines des données
        $rootProperties = $this->discoverData($this->data, [
            'recursive' => false,
        ]);

        // Retour positif si pas de propriétés racines
        return 0 === count($rootProperties);
    }

    /**
     * @internal
     *
     * @param mixed $data
     */
    private function discoverData($data, array $context = []): array
    {
        // Normalisation du contexte
        $context = array_merge([
            'path' => !empty($context['path']) ? $context['path'] : null,
            'recursive' => true,
            'excluded_classes' => isset($context['excluded_classes']) ? (array) $context['excluded_classes'] : [],
        ], $context);

        // Définition des valeurs par défaut selon les données
        $values = $data;

        // Si pas de données
        if (null === $data) {
            // Retour d'un tableau vide
            return [];
        }

        // Si les valeurs ne sont pas itérables
        if (!is_iterable($values)) {
            // Si les valeurs sont sous forme d'objet standard
            if ($values instanceof stdClass) {
                // Récupération des propriétés de l'objet
                $values = get_object_vars($values);
            } else {
                // Récupération du sérialiseur
                $serializer = self::getSerializer();

                // Si le sérialiseur ne supporte pas les données
                if (!$serializer->supportsNormalization($values)) {
                    // Objectization des données
                    $values = (object) $values;
                }

                // Normalisation des données
                $values = $serializer->normalize($values);

                // Retour de données iterables
                $values = is_iterable($values) ? $values : get_object_vars((object) $values);
            }
        }

        // Si les données sont sous forme d'objet non standard
        if (is_object($data) && !($data instanceof stdClass)) {
            // Récupération de la classe de l'objet
            $className = get_class($data);

            // Si la classe est exclut dans le contexte
            if (in_array($className, $context['excluded_classes'])) {
                // Retour d'un tableau vide
                return [];
            }

            // Exclusion de la classe que l'on analyse
            $context['excluded_classes'][] = $className;
        }

        // Si les données ne sont toujours pas itérables
        if (!is_iterable($values)) {
            // Retour d'un tableau vide
            return [];
        }

        // Initialisation du préfixe du chemin
        $pathPrefix = $context['path'] ?: '';

        // Initialisation du résultat
        $result = [];

        // Pour chaque valeur dans les données itérables
        foreach ($values as $key => $value) {
            // Définition du suffixe à ajouter dans le chemin
            $suffix = is_object($data) ? sprintf('%s%s', $pathPrefix ? '.' : '', $key) : sprintf('[%s]', $key);

            // Mise-à-jour de la clé selon le préfixe de ce niveau
            $key = $pathPrefix.$suffix;

            // Si la valeur est un objet ou un tableau
            if (is_object($value) || is_iterable($value)) {
                // Si on a activé l'option de récursivité
                if (true === $context['recursive']) {
                    // Découverte des valeurs par récursivité
                    $result = array_merge($result, $this->discoverData($value, array_merge($context, [
                        'path' => $key,
                    ])));

                    // Valeur suivante
                    continue;
                }
            }

            // Si la clé est lisible par l'accesseur de propriété
            if ($this->isReadable($key)) {
                // Enregistrement de la valeur non découvrable
                $result[$key] = $value;
            }
        }

        // Retour des valeurs
        return $result;
    }

    /**
     * @throws OutOfBoundsException     when the path is not readable
     * @throws InvalidArgumentException when a payload cannot be created with target value
     */
    public function slice(string $path): Payload
    {
        // Si le chemin n'est pas lisible
        if (!$this->isReadable($path)) {
            throw new OutOfBoundsException(sprintf('The path "%s" is not readable', $path));
        }

        $value = $this->get($path);

        // Si on ne peut pas créer de payload avec la valeur cible
        if (!self::supports($value)) {
            throw new InvalidArgumentException(sprintf('Cannot slice the payload at path "%s" with type "%s"', $path, gettype($value)));
        }

        // Retour d'un nouveau payload selon la valeur
        return new Payload($value);
    }

    /**
     * @param mixed|null $value
     *
     * @throws OutOfBoundsException when the path is not writable
     * @throws RuntimeException     when setting value failed
     */
    public function set(string $path, $value = null): self
    {
        // Si le chemin n'est pas writable
        if (!$this->isWritable($path)) {
            throw new OutOfBoundsException(sprintf('The path "%s" is not writable', $path));
        }

        try {
            // Enregistrement de la valeur de la propriété
            $this->propertyAccessor->setValue($this->data, $path, $value);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to write in path "%s" - %s', $path, $e->getMessage()), 0, $e);
        }

        // Retour du payload
        return $this;
    }

    /**
     * @param mixed|null $defaultValue
     *
     * @return mixed|null
     */
    public function get(string $path, $defaultValue = null)
    {
        if (!$this->isReadable($path)) {
            return $defaultValue;
        }

        return $this->propertyAccessor->getValue($this->data, $path);
    }

    public function isReadable(string $path): bool
    {
        return $this->propertyAccessor->isReadable($this->data, $path);
    }

    public function isWritable(string $path): bool
    {
        return $this->propertyAccessor->isWritable($this->data, $path);
    }

    /**
     * @param mixed $data
     *
     * @throws InvalidArgumentException when the type of data is not supported
     */
    public function setData($data): self
    {
        // Si pas d'objet ni de données itérables
        if (!self::supports($data)) {
            throw new InvalidArgumentException(sprintf('Expected data of type "object|iterable", "%s" given', gettype($data)));
        }

        // Hydratation
        $this->data = $data;

        return $this;
    }

    /**
     * @return object|iterable
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When encoding failed
     *
     * @return scalar
     */
    public function toJson(array $context = [])
    {
        return $this->encode(self::JSON_FORMAT, $context);
    }

    /**
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When encoding failed
     *
     * @return scalar
     */
    public function toXml(array $context = [])
    {
        return $this->encode(self::XML_FORMAT, $context);
    }

    /**
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When encoding failed
     *
     * @return scalar
     */
    public function toYaml(array $context = [])
    {
        return $this->encode(self::YAML_FORMAT, $context);
    }

    /**
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When encoding failed
     *
     * @return scalar
     */
    public function toCsv(array $context = [])
    {
        return $this->encode(self::CSV_FORMAT, $context);
    }

    /**
     * @throws InvalidArgumentException When the format is not supported
     * @throws RuntimeException         When encoding failed
     *
     * @return scalar
     */
    public function encode(string $format, array $context = [])
    {
        if (!self::getSerializer()->supportsDecoding($format, $context)) {
            throw new InvalidArgumentException(sprintf('The format "%s" is not supported', $format));
        }

        try {
            return self::getSerializer()->encode($this->data, $format, $context);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to encode data to format "%s" - %s', $format, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @see https://www.php.net/manual/fr/function.http-build-query.php
     *
     * @throws RuntimeException On failure
     */
    public function buildHttpQuery(string $numericPrefix = null, string $argSeparator = null, int $encType = PHP_QUERY_RFC1738): string
    {
        try {
            return http_build_query($this->data, $numericPrefix, $argSeparator, $encType);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to build HTTP query from data - %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @static
     */
    public static function getSerializer(): Serializer
    {
        if (null === self::$serializer) {
            self::$serializer = new Serializer([
                    new ObjectNormalizer(null, null, new PropertyAccessor()),
                ],
                [
                    new JsonEncoder(new JsonEncode(), new JsonDecode()),
                    new XmlEncoder(),
                    new YamlEncoder(),
                    new CsvEncoder([
                        CsvEncoder::AS_COLLECTION_KEY => true,
                    ]),
                ]
            );
        }

        return self::$serializer;
    }
}
