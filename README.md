# Wsdl generator used in Prado

Generates a WSDL document from a SOAP provider class by reading its doc comments.

## Doc tags

| Tag | Placed on | Effect |
|---|---|---|
| `@soapmethod` | a public method | The method becomes a WSDL operation. Its `@param` and `@return` types become the request and response parts. |
| `@soapproperty` | a property | The property becomes an element of its class's `complexType`. The type comes from the property's `@var` tag. |
| `@soaptype` | the provider class | The named class is written to the WSDL `<types>` section even when no operation signature refers to it. |

`@soaptype` covers methods that return mixed results: the operation declares `mixed`, and each
shape the method can return is declared separately so the client knows all of them.

```php
/**
 * @soaptype Person
 * @soaptype Address[]
 */
class MyProvider
{
	/**
	 * Look up a record.
	 * @param string $key the key to look up
	 * @return mixed a Person or an array of Address
	 * @soapmethod
	 */
	public function lookup($key) { /* ... */ }
}
```

A `[]` suffix declares the array form, which also declares the element type. Type names carry no
namespace, matching the names `@param` and `@return` use.

`@soapproperty` supports `nillable`, `minOccurs` and `maxOccurs`, written in braces after the
variable name:

```php
/**
 * @soapproperty
 * @var int $zip {nillable=1, minOccurs=0}
 */
public $zip;
```
