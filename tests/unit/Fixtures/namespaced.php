<?php

/**
 * A provider and its types in a namespace, as a PSR-4 project declares them.
 *
 * The generator reflects on the fully qualified name written in a doc comment,
 * and writes it into the document with each separator replaced by a dot. The
 * tags below name the types both with and without a leading separator.
 */

namespace Prado\Wsdl\Test\Unit\Fixtures;

/**
 * Quotes prices.
 *
 * @soaptype \Prado\Wsdl\Test\Unit\Fixtures\Money
 * @soaptype Prado\Wsdl\Test\Unit\Fixtures\Quote[]
 */
class QuoteProvider
{
	/**
	 * Quotes a symbol.
	 * @param string $symbol the symbol to quote
	 * @param \Prado\Wsdl\Test\Unit\Fixtures\Money $limit the price limit
	 * @return Prado\Wsdl\Test\Unit\Fixtures\Quote the quote
	 * @soapmethod
	 */
	public function quote($symbol, $limit = null)
	{
		$quote = new Quote();
		$quote->symbol = $symbol;
		$quote->price = new Money();
		$quote->price->amount = 1.5;
		$quote->price->currency = $limit->currency ?? 'USD';
		return $quote;
	}

	/**
	 * Lists the quotes of the day.
	 * @return Prado\Wsdl\Test\Unit\Fixtures\Quote[] the quotes
	 * @soapmethod
	 */
	public function history()
	{
		return [$this->quote('ABC'), $this->quote('XYZ')];
	}
}

class Quote
{
	/**
	 * @soapproperty
	 * @var string $symbol
	 */
	public $symbol;

	/**
	 * @soapproperty
	 * @var Prado\Wsdl\Test\Unit\Fixtures\Money $price
	 */
	public $price;
}

class Money
{
	/**
	 * @soapproperty
	 * @var float $amount
	 */
	public $amount;

	/**
	 * @soapproperty
	 * @var string $currency
	 */
	public $currency;
}

/**
 * Serves QuoteProvider in the document and literal style. The SOAP server of
 * PHP hands a method the request wrapper as one object and encodes what the
 * method returns as the response wrapper, so each method unwraps and wraps.
 */
class WrappedQuoteProvider
{
	/**
	 * @param object $request the quote wrapper, holding symbol and limit
	 * @return array<string, Quote> the quoteResponse wrapper
	 */
	public function quote($request)
	{
		return ['return' => (new QuoteProvider())->quote($request->symbol, $request->limit ?? null)];
	}

	/**
	 * @return array<string, Quote[]> the historyResponse wrapper
	 */
	public function history()
	{
		return ['return' => (new QuoteProvider())->history()];
	}
}
