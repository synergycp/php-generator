<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\PhpGenerator;

use Nette;
use Nette\InvalidStateException;


/**
 * Namespaced part of a PHP file.
 *
 * Generates:
 * - namespace statement
 * - variable amount of use statements
 * - one or more class declarations
 */
final class PhpNamespace
{
	use Nette\SmartObject;

	public const
		NAME_NORMAL = 'n',
		NAME_FUNCTION = 'f',
		NAME_CONSTANT = 'c';

	/** @var string */
	private $name;

	/** @var bool */
	private $bracketedSyntax = false;

	/** @var string[] */
	private $uses = [];

	/** @var string[] */
	private $lowerUses = [];

	/** @var ClassType[] */
	private $classes = [];

	/** @var GlobalFunction[] */
	private $functions = [];


	public function __construct(string $name)
	{
		if ($name !== '' && !Helpers::isNamespaceIdentifier($name)) {
			throw new Nette\InvalidArgumentException("Value '$name' is not valid name.");
		}
		$this->name = $name;
	}


	public function getName(): string
	{
		return $this->name;
	}


	/**
	 * @return static
	 * @internal
	 */
	public function setBracketedSyntax(bool $state = true): self
	{
		$this->bracketedSyntax = $state;
		return $this;
	}


	public function hasBracketedSyntax(): bool
	{
		return $this->bracketedSyntax;
	}


	/** @deprecated  use hasBracketedSyntax() */
	public function getBracketedSyntax(): bool
	{
		return $this->bracketedSyntax;
	}


	/**
	 * @throws InvalidStateException
	 * @return static
	 */
	public function addUse(string $name, string $alias = null, string &$aliasOut = null): self
	{
		if (
			!Helpers::isNamespaceIdentifier($name, true)
			|| (Helpers::isIdentifier($name) && isset(Helpers::KEYWORDS[strtolower($name)]))
		) {
			throw new Nette\InvalidArgumentException("Value '$name' is not valid class name.");
		} elseif ($alias && (!Helpers::isIdentifier($alias) || isset(Helpers::KEYWORDS[strtolower($alias)]))) {
			throw new Nette\InvalidArgumentException("Value '$alias' is not valid alias.");
		}

		$name = ltrim($name, '\\');
		if ($alias === null) {
			$base = Helpers::extractShortName($name);
			$counter = null;
			do {
				$alias = $base . $counter;
				$counter++;
			} while (($used = $this->lowerUses[strtolower($alias)] ?? null) && strcasecmp($used, $name) !== 0);

		} elseif (($used = $this->lowerUses[strtolower($alias)] ?? null) && strcasecmp($used, $name) !== 0) {
			throw new InvalidStateException(
				"Alias '$alias' used already for '{$used}', cannot use for '$name'."
			);
		}

		$aliasOut = $alias;
		$this->uses[$alias] = $this->lowerUses[strtolower($alias)] = $name;
		asort($this->uses);
		return $this;
	}


	/** @return string[] */
	public function getUses(): array
	{
		return $this->uses;
	}


	/** @deprecated  use simplifyName() */
	public function unresolveName(string $name): string
	{
		return $this->simplifyName($name);
	}


	public function simplifyType(string $type): string
	{
		return preg_replace_callback('~[\w\x7f-\xff\\\\]+~', function ($m) { return $this->simplifyName($m[0]); }, $type);
	}


	public function simplifyName(string $name): string
	{
		if (isset(Helpers::KEYWORDS[strtolower($name)]) || $name === '') {
			return $name;
		}
		$name = ltrim($name, '\\');
		$res = self::startsWith($name, $this->name . '\\')
			&& ($short = substr($name, strlen($this->name) + 1))
			&& !isset($this->lowerUses[strtolower(explode('\\', $short)[0])])
			? $short
			: null;

		foreach ($this->uses as $alias => $original) {
			if (self::startsWith($name . '\\', $original . '\\')) {
				$short = $alias . substr($name, strlen($original));
				if (!isset($res) || strlen($res) > strlen($short)) {
					$res = $short;
				}
			}
		}

		return $res ?? ($this->name ? '\\' : '') . $name;
	}


	/** @return static */
	public function add(ClassType $class): self
	{
		$name = $class->getName();
		if ($name === null) {
			throw new Nette\InvalidArgumentException('Class does not have a name.');
		}
		$this->addUse($this->name . '\\' . $name, $name);
		$this->classes[$name] = $class;
		return $this;
	}


	public function addClass(string $name): ClassType
	{
		$this->add($class = new ClassType($name, $this));
		return $class;
	}


	public function addInterface(string $name): ClassType
	{
		return $this->addClass($name)->setType(ClassType::TYPE_INTERFACE);
	}


	public function addTrait(string $name): ClassType
	{
		return $this->addClass($name)->setType(ClassType::TYPE_TRAIT);
	}


	public function addEnum(string $name): ClassType
	{
		return $this->addClass($name)->setType(ClassType::TYPE_ENUM);
	}


	public function addFunction(string $name): GlobalFunction
	{
		return $this->functions[$name] = new GlobalFunction($name);
	}


	/** @return ClassType[] */
	public function getClasses(): array
	{
		return $this->classes;
	}


	/** @return GlobalFunction[] */
	public function getFunctions(): array
	{
		return $this->functions;
	}


	public static function startsWith(string $a, string $b): bool
	{
		return strncasecmp($a, $b, strlen($b)) === 0;
	}


	public function __toString(): string
	{
		try {
			return (new Printer)->printNamespace($this);
		} catch (\Throwable $e) {
			if (PHP_VERSION_ID >= 70400) {
				throw $e;
			}
			trigger_error('Exception in ' . __METHOD__ . "(): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}", E_USER_ERROR);
			return '';
		}
	}
}
