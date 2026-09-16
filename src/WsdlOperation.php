<?php

/**
 * WsdlOperation file.
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
 * @author Wei Zhuo <weizhuo[at]gmail[dot]com>
 */

namespace Prado\Wsdl;

/**
 * Represents a WSDL Operation. This is exported for the portTypes and bindings
 * section of the soap service
 * @author 		Marcus Nyeholt		<tanus@users.sourceforge.net>
 * @author Wei Zhuo <weizhuo[at]gmail[dot]com>
 */
class WsdlOperation
{
	/**
	 * The name of the operation.
	 * @var string
	 */
	private string $operationName;

	/**
	 * The documentation of the operation.
	 * @var string
	 */
	private string $documentation;

	/**
	 * The request message of the operation.
	 * @var ?WsdlMessage
	 */
	private ?WsdlMessage $inputMessage = null;

	/**
	 * The response message of the operation.
	 * @var ?WsdlMessage
	 */
	private ?WsdlMessage $outputMessage = null;

	/**
	 * Creates a new operation.
	 * @param mixed $name The name of the operation, a string, or a value coerced
	 * to one as interpolation coerced it before the properties carried types
	 * @param mixed $doc The documentation of the operation, coerced the same way
	 */
	public function __construct($name, $doc = '')
	{
		// The properties carry types, and did not before. Coerce at the boundary,
		// so a caller that passed something else still gets what it always got.
		$this->operationName = (string) $name;
		$this->documentation = (string) $doc;
	}

	/**
	 * Sets the request message of the operation.
	 * @param WsdlMessage $msg The request message
	 * @return void
	 */
	public function setInputMessage(WsdlMessage $msg)
	{
		$this->inputMessage = $msg;
	}

	/**
	 * Sets the response message of the operation.
	 * @param WsdlMessage $msg The response message
	 * @return void
	 */
	public function setOutputMessage(WsdlMessage $msg)
	{
		$this->outputMessage = $msg;
	}

	/**
	 * The binding style the operation is written for.
	 * @var string
	 */
	private string $bindingStyle = Wsdl::STYLE_RPC;

	/**
	 * Sets the binding style the operation is written for. The style is carried
	 * rather than passed, so the signature of {@see setMessageElements} is the one
	 * it has always had.
	 * @param string $value The binding style
	 * @return void
	 * @since 1.2
	 */
	public function setBindingStyle($value)
	{
		$this->bindingStyle = $value;
	}

	/**
	 * Gets the binding style the operation is written for.
	 * @return string The binding style
	 * @since 1.2
	 */
	public function getBindingStyle()
	{
		return $this->bindingStyle;
	}

	/**
	 * Gets the name of the operation.
	 * @return string The name
	 * @since 1.2
	 */
	public function getName()
	{
		return $this->operationName;
	}

	/**
	 * Gets the request message of the operation.
	 * @return ?WsdlMessage The request message
	 * @since 1.2
	 */
	public function getInputMessage()
	{
		return $this->inputMessage;
	}

	/**
	 * Gets the response message of the operation.
	 * @return ?WsdlMessage The response message
	 * @since 1.2
	 */
	public function getOutputMessage()
	{
		return $this->outputMessage;
	}

	/**
	 * Sets the message elements for this operation into the wsdl document. A
	 * document and literal message names the global element wrapping its parts,
	 * where an rpc message carries one part per parameter.
	 * @param \DOMElement $wsdl The parent element for the messages
	 * @param \DOMDocument $dom The document the messages are created in
	 * @return void
	 */
	public function setMessageElements(\DOMElement $wsdl, \DOMDocument $dom)
	{
		if ($this->bindingStyle === Wsdl::STYLE_DOCUMENT) {
			$wsdl->appendChild($this->inputMessage->getDocumentMessageElement($dom, $this->operationName));
			$wsdl->appendChild($this->outputMessage->getDocumentMessageElement($dom, $this->operationName . 'Response'));
			return;
		}

		$wsdl->appendChild($this->inputMessage->getMessageElement($dom));
		$wsdl->appendChild($this->outputMessage->getMessageElement($dom));
	}

	/**
	 * Gets the port operation for this operation.
	 * @param \DOMDocument $dom The document the messages are created in
	 * @return \DOMElement The element representing this port
	 */
	public function getPortOperation(\DOMDocument $dom)
	{
		$operation = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:operation');
		$operation->setAttribute('name', $this->operationName);

		$documentation = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:documentation', htmlentities($this->documentation));
		$input = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:input');
		$input->setAttribute('message', 'tns:' . $this->inputMessage->getName());
		$output = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:output');
		$output->setAttribute('message', 'tns:' . $this->outputMessage->getName());

		$operation->appendChild($documentation);
		$operation->appendChild($input);
		$operation->appendChild($output);

		return $operation;
	}

	/**
	 * Build the binding operations.
	 * TODO: Still quite incomplete with all the things being stuck in, I don't understand
	 * a lot of it, and it's mostly copied from the output of nusoap's wsdl output.
	 * @param \DOMDocument $dom The document the binding is created in
	 * @param string $namespace The namespace this binding is in
	 * @param string $style The binding style
	 * @return \DOMElement The element representing this binding.
	 */
	public function getBindingOperation(\DOMDocument $dom, $namespace, $style = 'rpc')
	{
		$operation = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:operation');
		$operation->setAttribute('name', $this->operationName);

		$soapOperation = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/soap/', 'soap:operation');
		$method = $this->operationName;
		$soapOperation->setAttribute('soapAction', $namespace . '#' . $method);
		$soapOperation->setAttribute('style', $style);

		$input = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:input');
		$output = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:output');

		$soapBody = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/soap/', 'soap:body');
		if ($style === Wsdl::STYLE_DOCUMENT) {
			// The Basic Profile prohibits SOAP encoding, and an encodingStyle with it.
			$soapBody->setAttribute('use', 'literal');
		} else {
			$soapBody->setAttribute('use', 'encoded');
			$soapBody->setAttribute('namespace', $namespace);
			$soapBody->setAttribute('encodingStyle', 'http://schemas.xmlsoap.org/soap/encoding/');
		}
		$input->appendChild($soapBody);
		$output->appendChild(clone $soapBody);

		$operation->appendChild($soapOperation);
		$operation->appendChild($input);
		$operation->appendChild($output);

		return $operation;
	}
}
