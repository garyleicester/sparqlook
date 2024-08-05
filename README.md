**SPARQLook**

A simple, lightweight, single php file tool for exploring SPARQL endpoints. It should be easy to host on any Apache webserver, for instance.

Try it at https://allophone.hamster.coffee/sparqlook.php

Some example endpoints to try -

https://id.cabi.org/PoolParty/sparql/cabt

https://metadata.ilo.org/PoolParty/sparql/thesaurus

https://cgi.vocabs.ga.gov.au/sparql/

https://dbpedia.org/sparql

I'm intuitive. And nosey. And impatient. I want to take a look. I built this to let me poke around a(ny) SPARQL endpoint.

Best if you know the URI you want to start at as well as the endpoint address, but leaving the URI blank will retrieve some suggested starting points, using a number of 'initial explore' strategies that can be added to in the code.

I mostly work with SKOS thesauri, so currently leans a little too heavily that way.
