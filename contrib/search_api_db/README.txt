$Id$

Database search
---------------

This module provides a database based implementation of the Search API. The
database and target to use for storing and accessing the indexes can be selected
when creating a new server.

All Search API datatypes are supported by using appropriate SQL datatypes for
their respective columns (with "String"/"URI", and "Integer"/"Duration" being
equivalent).

The "direct" parse mode for queries will result in a simple splitting of the
query string into keys. Additionally, search keys containing whitespace will be
split, as searching for phrases is currently not supported.

Due to SQL limitations, fulltext searches are always case-insensitive.

If you feel some service option is missing, or have other ideas for improving
this implementation, please file a feature request in the project's issue queue,
at [http://drupal.org/project/issues/search_api].
