<?php

namespace Prado\Wsdl\Test\Unit;

use SoapClient;
use SoapServer;

/**
 * A SOAP client whose transport hands each request to a server in the same
 * process. A call then exercises both ends of a generated document, the client
 * encoding the request from it and the server decoding and answering from it,
 * without a network.
 */
class InProcessSoapClient extends SoapClient
{
	/**
	 * The server that answers the requests.
	 * @var SoapServer
	 */
	private SoapServer $server;

	/**
	 * Creates a client reading a document and calling a server.
	 * @param string $wsdl The document to read
	 * @param array<string, mixed> $options The client options
	 * @param SoapServer $server The server that answers, reading the same document
	 */
	public function __construct($wsdl, array $options, SoapServer $server)
	{
		parent::__construct($wsdl, $options);
		$this->server = $server;
	}

	/**
	 * Hands the request to the server and returns what it wrote.
	 * @param string $request The SOAP request
	 * @param string $location The service URI, unused
	 * @param string $action The SOAP action, unused
	 * @param int $version The SOAP version, unused
	 * @param bool $oneWay Whether no response is expected
	 * @return ?string The SOAP response
	 */
	public function __doRequest($request, $location, $action, $version, $oneWay = false): ?string
	{
		ob_start();
		try {
			$this->server->handle($request);
		} finally {
			$response = ob_get_clean();
		}
		return $oneWay ? null : (string) $response;
	}
}
