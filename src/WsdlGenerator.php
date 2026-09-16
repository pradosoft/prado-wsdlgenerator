<?php

/**
 * WsdlGenerator file.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the BSD License.
 *
 * Copyright(c) 2005 by Marcus Nyeholt. All rights reserved.
 *
 * To contact the author write to {@link mailto:tanus@users.sourceforge.net Marcus Nyeholt}
 * This file is part of the PRADO framework from {@link http://www.xisc.com}
 *
 * @author Marcus Nyeholt		<tanus@users.sourceforge.net>
 */

namespace Prado\Wsdl;

/**
 * Generator for the wsdl.
 * Special thanks to Cristian Losada for implementing the Complex Types section of the WSDL.
 * @author 		Marcus Nyeholt		<tanus@users.sourceforge.net>
 * @author 		Cristian Losada		<cristian@teaxul.com>
 */
class WsdlGenerator
{
	/**
	 * The opening of a doc comment tag that takes an argument: the start of a line
	 * or the opening of the comment, then the asterisks and spaces that lead into
	 * the tag. Such a tag reads exactly like prose naming it, so position is the
	 * only thing that separates the two.
	 */
	private const TAG_START = '(?:^|\\/\\*\\*)[ \\t*]*@';

	/**
	 * What may follow a marker tag: horizontal space, then the end of the line or
	 * the end of the comment. A marker takes no argument, so anything else after
	 * it means the comment is discussing the tag rather than carrying it.
	 */
	private const TAG_END = '[ \\t\\r]*(?:\\*\\/|$)';

	/**
	 * The singleton instance.
	 * @var ?WsdlGenerator
	 */
	private static ?WsdlGenerator $instance = null;

	/**
	 * The complex types to use in the wsdl, indexed by type name. An array type
	 * holds an empty string, because its element is derived from its name.
	 * @var array<string, array<int, array<string, mixed>>|string>
	 */
	private array $types = [];

	/**
	 * The document the generated wsdl is built into.
	 * @var ?Wsdl
	 */
	private ?Wsdl $wsdlDocument = null;

	/**
	 * The actual wsdl string.
	 * @var string
	 */
	private string $wsdl = '';

	/**
	 * The singleton instance for the generator
	 * @return WsdlGenerator The instance
	 */
	public static function getInstance()
	{
		if (null === self::$instance) {
			self::$instance = new WsdlGenerator();
		}
		return self::$instance;
	}

	/**
	 * Get the Wsdl generated
	 * @return string The Wsdl for this wsdl
	 */
	public function getWsdl()
	{
		return $this->wsdl;
	}

	/**
	 * Generates WSDL for a passed in class, and saves it in the current object. The
	 * WSDL can then be retrieved by calling
	 * @param string $className The name of the class to generate for
	 * @param string $serviceUri The URI of the service that handles this WSDL
	 * @param string $encoding character encoding.
	 * @return void
	 */
	public function generateWsdl($className, $serviceUri = '', $encoding = '')
	{
		$this->types = [];
		$this->wsdl = '';
		$this->wsdlDocument = new Wsdl($className, $serviceUri, $encoding);

		$classReflect = new \ReflectionClass($className);
		$this->processTypeTags($classReflect);
		$methods = $classReflect->getMethods();

		foreach ($methods as $method) {
			// Only process public methods
			if ($method->isPublic()) {
				$this->processMethod($method);
			}
		}

		foreach ($this->types as $type => $elements) {
			$this->wsdlDocument->addComplexType($type, $elements);
		}

		$this->wsdl = $this->wsdlDocument->getWsdl();
	}

	/**
	 * Static method that generates and outputs the generated wsdl
	 * @param string $className The name of the class to export
	 * @param string $serviceUri The URI of the service that handles this WSDL
	 * @param string $encoding character encoding.
	 * @return string The generated wsdl
	 */
	public static function generate($className, $serviceUri = '', $encoding = '')
	{
		$generator = WsdlGenerator::getInstance();
		$generator->generateWsdl($className, $serviceUri, $encoding);
		//header('Content-type: text/xml');
		return $generator->getWsdl();
		//exit();

	}

	/**
	 * Declares the complex types named by the \@soaptype tags in the class doc comment.
	 * A type declared this way is written to the WSDL even when no \@soapmethod
	 * signature refers to it, which is how a method returning mixed results makes
	 * every shape it can return known to the client. The tag takes a class name,
	 * optionally suffixed with [] for the array form:
	 * <code>
	 * \@soaptype MyRecord
	 * \@soaptype MyRecord[]
	 * </code>
	 * The class name follows the same grammar as \@param and \@return, so it carries
	 * no namespace.
	 * @param \ReflectionClass<object> $classReflect The class to read the tags from
	 * @return void
	 * @since 1.2
	 */
	protected function processTypeTags(\ReflectionClass $classReflect)
	{
		$comment = $classReflect->getDocComment();
		if ($comment === false) {
			return;
		}

		if (preg_match_all('/' . self::TAG_START . 'soaptype\s+(\w+(\[\s*\])?)/mi', $comment, $matches)) {
			foreach ($matches[1] as $type) {
				$this->convertType(preg_replace('/\s+/', '', $type));
			}
		}
	}

	/**
	 * Tells whether a doc comment carries a marker tag. A marker takes no
	 * argument, so it ends its line, wherever on the line it is written. A
	 * comment that goes on to say something about the tag is discussing it:
	 * <code>
	 * \@soapmethod                     carries the tag
	 * Adds two numbers. \@soapmethod    carries the tag
	 * Discusses the \@soapmethod tag.   does not
	 * </code>
	 * @param false|string $comment The doc comment to read, as reflection returns it
	 * @param string $tag The tag to look for, without its leading at sign
	 * @return bool Whether the comment carries the tag
	 * @since 1.2
	 */
	protected static function hasTag($comment, $tag)
	{
		if (!is_string($comment)) {
			return false;
		}

		return preg_match('/@' . $tag . self::TAG_END . '/mi', $comment) === 1;
	}

	/**
	 * Process a method found in the passed in class.
	 * @param \ReflectionMethod $method The method to process
	 * @return void
	 */
	protected function processMethod(\ReflectionMethod $method)
	{
		$comment = $method->getDocComment();
		if (!self::hasTag($comment, 'soapmethod')) {
			return;
		}
		$comment = preg_replace("/(^[\\s]*\\/\\*\\*)
                                 |(^[\\s]\\*\\/)
                                 |(^[\\s]*\\*?\\s)
                                 |(^[\\s]*)
                                 |(^[\\t]*)/ixm", "", $comment);

		$comment = str_replace("\r", "", $comment);
		$comment = preg_replace("/([\\t])+/", "\t", $comment);
		$commentLines = explode("\n", $comment);

		$methodDoc = '';
		$params = [];
		$return = [];
		$gotDesc = false;
		$gotParams = false;

		foreach ($commentLines as $line) {
			if ($line == '') {
				continue;
			}
			if ($line[0] == '@') {
				$gotDesc = true;
				if (preg_match('/^@param\s+([\w\[\]()]+)\s+\$([\w()]+)\s*(.*)/i', $line, $match)) {
					$param = [];
					$param['type'] = $this->convertType($match[1]);
					$param['name'] = $match[2];
					$param['desc'] = $match[3];
					$params[] = $param;
				} elseif (preg_match('/^@return\s+([\w\[\]()]+)\s*(.*)/i', $line, $match)) {
					$gotParams = true;
					$return['type'] = $this->convertType($match[1]);
					$return['desc'] = $match[2];
					$return['name'] = 'return';
				}
			} else {
				if (!$gotDesc) {
					$methodDoc .= trim($line);
				} elseif (!$gotParams) {
					if (count($params) > 0) {
						$params[count($params) - 1]['desc'] .= trim($line);
					}
				} else {
					if ($line == '*/') {
						continue;
					}
					$return['desc'] .= trim($line);
				}
			}
		}

		$methodName = $method->getName();
		$operation = new WsdlOperation($methodName, $methodDoc);

		$operation->setInputMessage(new WsdlMessage($methodName . 'Request', $params));
		$operation->setOutputMessage(new WsdlMessage($methodName . 'Response', [$return]));

		$this->wsdlDocument->addOperation($operation);

	}

	/**
	 * Converts from a PHP type into a WSDL type. This is borrowed from
	 * Cerebral Cortex (let me know and I'll remove asap).
	 *
	 * TODO: date and dateTime
	 * @param string $type The php type to convert
	 * @return string The XSD type.
	 */
	private function convertType($type): string
	{
		switch ($type) {
			case 'string':
			case 'str':
				return 'xsd:string';
			case 'int':
			case 'integer':
				return 'xsd:int';
			case 'float':
			case 'double':
				return 'xsd:float';
			case 'boolean':
			case 'bool':
				return 'xsd:boolean';
			case 'date':
				return 'xsd:date';
			case 'time':
				return 'xsd:time';
			case 'dateTime':
				return 'xsd:dateTime';
			case 'array':
				return 'soap-enc:Array';
			case 'object':
				return 'xsd:struct';
			case 'mixed':
				return 'xsd:anyType';
			case 'void':
				return '';
			default:
				if (strpos($type, '[]')) {  // if it is an array
					$className = substr($type, 0, strlen($type) - 2);
					$type = $className . 'Array';
					$this->types[$type] = '';
					$this->convertType($className);
				} else {
					if (!isset($this->types[$type])) {
						$this->extractClassProperties($type);
					}
				}
				return 'tns:' . $type;
		}
	}

	/**
	 * Extract the type and the name of all properties of the $className class and saves it in the $types array
	 * This method extract properties from PHPDoc formatted comments for variables. Unfortunately the reflectionproperty
	 * class doesn't have a getDocComment method to extract comments about it, so we have to extract the information
	 * about the variables manually. Thanks heaps to Cristian Losada for implementing this.
	 * @param string $className The name of the class
	 */
	private function extractClassProperties($className): void
	{
		/**
		 * modified by Qiang Xue, Jan. 2, 2007
		 * Using Reflection's DocComment to obtain property definitions
		 * DocComment is available since PHP 5.1
		 */
		$reflection = new \ReflectionClass($className);
		$properties = $reflection->getProperties();
		foreach ($properties as $property) {
			$comment = $property->getDocComment();
			if (self::hasTag($comment, 'soapproperty')) {
				if (preg_match('/@var\s+([\w\.]+(\[\s*\])?)\s*?\$(.*)$/mi', $comment, $matches)) {
					// support nillable, minOccurs, maxOccurs attributes
					$nillable = $minOccurs = $maxOccurs = false;
					if (preg_match('/{(.+)}/', $matches[3], $attr)) {
						$matches[3] = str_replace($attr[0], '', $matches[3]);
						if (preg_match_all('/((\w+)\s*=\s*(\w+))/mi', $attr[1], $attr)) {
							foreach ($attr[2] as $id => $prop) {
								if (strcasecmp($prop, 'nillable') === 0) {
									$nillable = $attr[3][$id] ? 'true' : 'false';
								} elseif (strcasecmp($prop, 'minOccurs') === 0) {
									$minOccurs = (int) $attr[3][$id];
								} elseif (strcasecmp($prop, 'maxOccurs') === 0) {
									$maxOccurs = (int) $attr[3][$id];
								}
							}
						}
					}

					$param = [];
					$param['type'] = $this->convertType($matches[1]);
					$param['name'] = trim($matches[3]);
					$param['nil'] = $nillable;
					$param['minOc'] = $minOccurs;
					$param['maxOc'] = $maxOccurs;
					$this->types[$className][] = $param;

				}
			}
		}
	}
}
