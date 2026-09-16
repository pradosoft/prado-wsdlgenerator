# Changelog

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.2.0 (unreleased)

### Added

- `@soaptype` declares a complex type on the provider class, so a type reaches
  the WSDL even when no `@soapmethod` signature names it. A method returning
  mixed results uses it to declare every shape it can return
  ([pradosoft/prado#120](https://github.com/pradosoft/prado/issues/120)).
- `Wsdl::getArrayElementType()` resolves the element type of an array
  complexType.
- `WsdlGenerator::hasTag()` reads a marker tag from a doc comment.
- A unit test suite, static analysis at level 6, and continuous integration on
  PHP 8.1, 8.2 and 8.3.

### Fixed

- An array of a primitive declared its element as `tns:<type>`, a type no
  document declares. A schema validator rejects a reference that does not
  resolve. The element now takes its XSD type, and the aliases `str`, `integer`,
  `double`, `bool`, `mixed` and `object` resolve alongside the canonical names.
- `WsdlGenerator::generate()` returns a singleton that never cleared the types
  of the previous generation, so a service generated after another carried that
  service's complexTypes.
- A doc comment naming `@soapmethod` or `@soapproperty` in prose declared an
  operation or exported an element. A marker takes no argument, so it is read
  only where it ends its line.
- A `@return void` produced `<wsdl:part name="return" type=""/>`. A part with no
  type is not usable, and is now left out.
- A description continuing onto the line after a tag that was not `@param`
  indexed the parameter list at -1, raising two warnings and dropping the line.
- A service name holding `&`, `"` or `<` reached the parser as markup. The
  document failed to load and the build died dereferencing a null element,
  reporting the null rather than the name behind it.
- An encoding that closed its own attribute did the same. It is refused now,
  naming the value.
- The fallback service URI read `HTTP_HOST` and `PHP_SELF` without checking they
  were set, warning twice off a request.
- Ten `break` statements sat unreachable after a `return` in the type switch.

### Changed

- Requires PHP 8.1 or later, matching the floor of the Prado release that
  consumes this package. A project on an earlier PHP resolves 1.1.
- The source follows Prado's code style, and its properties carry types. A
  service name that is not a string, such as `null` or an array, now raises a
  `TypeError` where it was previously coerced.
- An invalid encoding raises an `InvalidArgumentException`, and a service name
  no document can hold raises a `RuntimeException`. Both were fatal errors
  before, so nothing that worked stops working, but the type of the failure has
  changed.

### Deprecated

- `Wsdl::getArrayTypePrefix()`, which resolves half of an element type. Use
  `Wsdl::getArrayElementType()`, which resolves the whole name.

## Upgrading from 1.1

Every doc comment that worked in 1.1 still works. A marker written on its own
line, opening a one-line comment, or closing a description on the same line is
read as before. Only a comment that names a marker and then goes on to say
something about it stops declaring anything, which is the behavior that made
prose export a method.

The generated document changes where the fixes above apply. A service returning
an array of a primitive, a method returning void, and a second service
generated in one process each produce a document that differs from 1.1. In each
case the 1.2 document is the correct one.

Nothing was removed from the public or protected API.

## 1.1

- Fix deprecated usage of curly braces.

## 1.0

- Import of the generator from the Prado framework.
