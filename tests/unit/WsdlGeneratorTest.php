<?php

namespace Prado\Wsdl\Test\Unit;

use Prado\Wsdl\WsdlGenerator;
use ReflectionClass;
use ReflectionException;

class WsdlGeneratorTest extends WsdlTestCase
{
	public function testGetInstanceReturnsTheSameGenerator(): void
	{
		$this->assertSame(WsdlGenerator::getInstance(), WsdlGenerator::getInstance());
		$this->assertInstanceOf(WsdlGenerator::class, WsdlGenerator::getInstance());
	}

	public function testGetWsdlIsEmptyBeforeGenerating(): void
	{
		$generator = new WsdlGenerator();
		$this->assertSame('', $generator->getWsdl());
	}

	public function testGenerateReturnsTheDocument(): void
	{
		$wsdl = WsdlGenerator::generate('WsdlTestProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $wsdl);
		$this->assertStringContainsString('WsdlTestProviderService', $wsdl);
	}

	public function testOnlyPublicSoapMethodsBecomeOperations(): void
	{
		$dom = $this->generate('WsdlTestProvider');
		$this->assertSame(['add'], $this->attributes($dom, '//wsdl:portType/wsdl:operation', 'name'));
	}

	public function testOperationCarriesItsParametersAndReturn(): void
	{
		$dom = $this->generate('WsdlTestProvider');
		$this->assertSame(['a' => 'xsd:int', 'b' => 'xsd:int'], $this->parts($dom, 'addRequest'));
		$this->assertSame(['return' => 'xsd:int'], $this->parts($dom, 'addResponse'));
	}

	public function testOperationCarriesItsDocumentation(): void
	{
		$dom = $this->generate('WsdlTestProvider');
		$documentation = $this->query($dom, '//wsdl:portType/wsdl:operation/wsdl:documentation');
		$this->assertSame('Adds two numbers.', $documentation->item(0)->textContent);
	}

	public function testScalarTypesConvertToTheirXsdEquivalent(): void
	{
		$dom = $this->generate('WsdlTestScalarProvider');
		$this->assertSame([
			'a' => 'xsd:string',
			'b' => 'xsd:string',
			'c' => 'xsd:int',
			'd' => 'xsd:int',
			'e' => 'xsd:float',
			'f' => 'xsd:float',
			'g' => 'xsd:boolean',
			'h' => 'xsd:boolean',
		], $this->parts($dom, 'scalarsRequest'));
	}

	public function testRemainingTypesConvertToTheirXsdEquivalent(): void
	{
		$dom = $this->generate('WsdlTestScalarProvider');
		$this->assertSame([
			'a' => 'xsd:date',
			'b' => 'xsd:time',
			'c' => 'xsd:dateTime',
			'd' => 'soap-enc:Array',
			'e' => 'xsd:struct',
			'f' => 'xsd:anyType',
		], $this->parts($dom, 'othersRequest'));
	}

	public function testSoapPropertiesBecomeComplexTypeElements(): void
	{
		$dom = $this->generate('WsdlTestNestedProvider');
		$elements = $this->attributes($dom, "//xsd:complexType[@name='WsdlTestAddress']/xsd:all/xsd:element", 'name');
		$this->assertSame(['street', 'zip'], $elements, 'a property without @soapproperty is left out');
	}

	public function testSoapPropertyAttributesReachTheElement(): void
	{
		$dom = $this->generate('WsdlTestNestedProvider');
		$zip = $this->element($dom, "//xsd:complexType[@name='WsdlTestAddress']/xsd:all/xsd:element[@name='zip']");
		$this->assertSame('xsd:int', $zip->getAttribute('type'));
		$this->assertSame('true', $zip->getAttribute('nillable'));
		$this->assertSame('0', $zip->getAttribute('minOccurs'));
		$this->assertSame('2', $zip->getAttribute('maxOccurs'));
	}

	public function testAComplexPropertyPullsInItsOwnType(): void
	{
		$dom = $this->generate('WsdlTestNestedProvider');
		$this->assertContains('WsdlTestPerson', $this->complexTypes($dom));
		$this->assertContains('WsdlTestAddress', $this->complexTypes($dom), 'the type of a property is declared too');
	}

	public function testATypeWithoutSoapPropertiesDeclaresNothing(): void
	{
		$dom = $this->generate('WsdlTestBlankTypeProvider');
		$this->assertNotContains('WsdlTestBlank', $this->complexTypes($dom));
	}

	public function testADocCommentWithoutTagsGeneratesAnEmptyMessage(): void
	{
		$dom = $this->generate('WsdlTestDocCommentProvider');
		$this->assertSame([], $this->parts($dom, 'bareRequest'));
		$this->assertSame([], $this->parts($dom, 'bareResponse'));
	}

	public function testDescriptionsContinueOntoTheFollowingLine(): void
	{
		$dom = $this->generate('WsdlTestDocCommentProvider');
		$this->assertSame(['a' => 'xsd:string'], $this->parts($dom, 'continuationRequest'));
		$this->assertSame(['return' => 'xsd:string'], $this->parts($dom, 'continuationResponse'));
	}

	/**
	 * A continuation line reaching the parser before any @param had no parameter
	 * to describe, and indexed the parameter list at -1.
	 */
	public function testAContinuationBeforeAnyParamRaisesNoWarning(): void
	{
		$raised = [];
		set_error_handler(function ($severity, $message) use (&$raised) {
			$raised[] = $message;
			return true;
		});

		try {
			$this->generate('WsdlTestDocCommentProvider');
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);
	}

	/**
	 * convertType() maps void to an empty string, which is not a usable part.
	 */
	public function testAVoidReturnProducesNoPart(): void
	{
		$dom = $this->generate('WsdlTestVoidProvider');
		$this->assertSame([], $this->parts($dom, 'doThingResponse'));
		$this->assertCount(1, $this->query($dom, "//wsdl:message[@name='doThingResponse']"));
	}

	/**
	 * generate() hands back a singleton, which used to carry the types of the
	 * previous generation into the next document.
	 */
	public function testAGenerationDoesNotInheritTheTypesOfTheLast(): void
	{
		$generator = WsdlGenerator::getInstance();

		$generator->generateWsdl('WsdlTestArrayProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertStringContainsString('stringArray', $generator->getWsdl());

		$generator->generateWsdl('WsdlTestVoidProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertStringNotContainsString('stringArray', $generator->getWsdl());
	}

	public function testTheDocumentOfAGenerationReplacesTheLast(): void
	{
		$generator = new WsdlGenerator();
		$generator->generateWsdl('WsdlTestProvider', 'http://example.com/soap', 'UTF-8');
		$first = $generator->getWsdl();

		$generator->generateWsdl('WsdlTestVoidProvider', 'http://example.com/soap', 'UTF-8');
		$this->assertNotSame($first, $generator->getWsdl());
	}

	public function testSoapTypeDeclaresATypeNoSignatureNames(): void
	{
		$dom = $this->generate('WsdlTestTypeTagProvider');
		$types = $this->complexTypes($dom);
		$this->assertContains('WsdlTestPerson', $types);
		$this->assertContains('WsdlTestAddressArray', $types);
		$this->assertContains('WsdlTestAddress', $types, 'the array form declares its element type as well');
	}

	public function testSoapTypeIsReadFromAOneLineDocComment(): void
	{
		$dom = $this->generate('WsdlTestOneLineTagProvider');
		$this->assertContains('WsdlTestPerson', $this->complexTypes($dom));
	}

	public function testSoapTypeAcceptsSpacedBrackets(): void
	{
		$dom = $this->generate('WsdlTestProseProvider');
		$this->assertContains('WsdlTestAddressArray', $this->complexTypes($dom));
	}

	/**
	 * The tag has to be in tag position. Prose naming it is not a declaration.
	 */
	public function testProseNamingTheTagDeclaresNothing(): void
	{
		$dom = $this->generate('WsdlTestProseProvider');
		$this->assertSame(['WsdlTestAddressArray', 'WsdlTestAddress'], $this->complexTypes($dom));
	}

	public function testAProviderWithoutTheTagDeclaresNothing(): void
	{
		$dom = $this->generate('WsdlTestNoTagProvider');
		$this->assertSame([], $this->complexTypes($dom));
	}

	public function testSoapTypeNamingAMissingClassFails(): void
	{
		$this->expectException(ReflectionException::class);
		$this->generate('WsdlTestBadTagProvider');
	}

	/**
	 * An array of a primitive used to name tns:<type>, which the document never
	 * declares. A schema validator rejects a reference that does not resolve.
	 *
	 * @dataProvider providerNameProvider
	 * @param string $className The provider to generate for
	 */
	public function testEveryTypeAGeneratedDocumentNamesIsDeclared($className): void
	{
		$this->assertEveryLocalTypeResolves($this->generate($className));
	}

	/**
	 * @return array<int, array<int, string>> The provider to generate for
	 */
	public static function providerNameProvider(): array
	{
		return [
			['WsdlTestProvider'],
			['WsdlTestScalarProvider'],
			['WsdlTestArrayProvider'],
			['WsdlTestVoidProvider'],
			['WsdlTestTypeTagProvider'],
			['WsdlTestNestedProvider'],
			['WsdlTestDocCommentProvider'],
		];
	}

	/**
	 * Every marker is read in tag position only, so a doc comment discussing one
	 * is prose.
	 */
	public function testProseNamingTheMethodMarkerDeclaresNoOperation(): void
	{
		$dom = $this->generate('WsdlTestProseMarkerProvider');
		$this->assertSame(['oneLineMarker'], $this->attributes($dom, '//wsdl:portType/wsdl:operation', 'name'));
	}

	public function testTheMethodMarkerIsReadFromAOneLineDocComment(): void
	{
		$dom = $this->generate('WsdlTestProseMarkerProvider');
		$this->assertSame(['return' => 'xsd:string'], $this->parts($dom, 'oneLineMarkerResponse'));
	}

	public function testProseNamingThePropertyMarkerExportsNoElement(): void
	{
		$dom = $this->generate('WsdlTestNestedProvider');
		$elements = $this->attributes($dom, "//xsd:complexType[@name='WsdlTestAddress']/xsd:all/xsd:element", 'name');
		$this->assertSame(['street', 'zip'], $elements);
		$this->assertNotContains('internal', $elements);
	}

	public function testThePropertyMarkerIsReadFromAOneLineDocComment(): void
	{
		$dom = $this->generate('WsdlTestProseMarkerProvider');
		$elements = $this->attributes($dom, "//xsd:complexType[@name='WsdlTestOneLineType']/xsd:all/xsd:element", 'name');
		$this->assertSame(['a'], $elements);
	}

	/**
	 * @dataProvider markerCommentProvider
	 * @param string $comment The doc comment to read
	 * @param bool $expected Whether the comment carries the tag
	 */
	public function testHasTagReadsATagOnlyInTagPosition($comment, $expected): void
	{
		$method = new \ReflectionMethod(WsdlGenerator::class, 'hasTag');
		$method->setAccessible(true);
		$this->assertSame($expected, $method->invoke(null, $comment, 'soapmethod'));
	}

	/**
	 * @return array<string, array{0: false|string, 1: bool}> The comment and what it carries
	 */
	public static function markerCommentProvider(): array
	{
		return [
			'on its own line' => ["/**\n * @soapmethod\n */", true],
			'opening the comment' => ['/** @soapmethod */', true],
			'leading tabs' => ["/**\n\t * @soapmethod\n\t */", true],
			'with other tags' => ["/**\n * Does a thing.\n * @soapmethod\n * @return string x\n */", true],
			'named in prose' => ['/** Discusses the @soapmethod tag. */', false],
			'mid sentence' => ["/**\n * Use the @soapmethod tag here.\n */", false],
			'a longer tag' => ["/**\n * @soapmethods\n */", false],
			'absent' => ["/**\n * Does a thing.\n */", false],
			'no doc comment' => [false, false],
		];
	}

	public function testProcessTypeTagsIgnoresAClassWithoutADocComment(): void
	{
		$generator = new WsdlGenerator();
		$generator->generateWsdl('WsdlTestProvider', 'http://example.com/soap', 'UTF-8');

		$this->callProtected($generator, 'processTypeTags', [new ReflectionClass(\WsdlTestUndocumented::class)]);

		$this->assertStringNotContainsString('complexType', $generator->getWsdl());
	}
}
