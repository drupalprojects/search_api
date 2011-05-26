Search API Views integration
----------------------------

This module integrates the Search API with the popular Views module [1],
allowing users to create views with filters, arguments, sorts and fields based
on any search index.

[1] http://drupal.org/project/views

"Facets block" display
----------------------
Most features should be clear to users of Views. However, the module also
provides a new display type, "Facets block", that might need some explanation.
This display type is only available, if the Search facets module is also
enabled.

The basic use of the block is to provide a list of links to the most popular
filter terms (i.e., the ones with the most results) for a certain category. For
example, you could provide a block listing the most popular authors, or taxonomy
terms, linking to searches for those, to provide some kind of landing page.

Please note that, due to limitations in Views, this display mode is shown for
views of all base tables, even though it only works for views based on Search
API indexes. For views of other base tables, this will just print an error
message.
The display will also always ignore the view's "Style" setting.

To use the display, specify the base path of the search you want to link to
(this enables you to also link to searches that aren't based on Views) and the
facet field to use (any indexed field can be used here, there needn't be a facet
defined for it). You'll then have the block available in the blocks
administration and can enable and move it at leisure.

You should note two things, though: First, if you want to display the block not
only on a few pages, you should in any case take care that it isn't displayed
on the search page, since that might confuse users.
Also, since the block executes a search query to retrieve the facets, its
display will potentially trigger other facet blocks to be displayed for that
search. To prevent this, set the other facet blocks to either not display on the
pages where the Views facet block is shown, or to ignore the search executed by
the block (recognizable by the "-facet_block" suffix).

Make fields in greater depths available
---------------------------------------
Currently, field handlers are only generated up to a nesting level of 2. E.g.,
for an index of nodes, you can display fields from the author's profile, but not
the URL of an image in that profile.
There are currently plans to solve this problem by providing proper
"relationships" to nested entities. However, until then, you can circumvent this
problem by setting the "search_api_views_max_fields_depth" variable to the
maximum level to generate field handlers for. The default behaviour is 2.
