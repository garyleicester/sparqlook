<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SparqLook</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            color: black;
            background-color: white;
            margin: 0;
            padding: 20px;
        }
        .input-container {
            display: flex;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .input-container input,
        .input-container button {
            padding: 10px;
            border: none;
            border-radius: 15px;
            margin-right: 10px;
            margin-bottom: 10px;
            flex-grow: 1;
            font-size: 14px;
        }
        #uriInput {
            text-align: left;
        }
        .input-container input {
            border: 1px solid #000;
        }
        .input-container button {
            background-color: black;
            color: white;
            cursor: pointer;
        }
        .input-container button:hover {
            background-color: #333;
        }
        .results {
            margin-top: 20px;
        }
        .predicate {
            margin-bottom: 10px;
            margin-top: 10px;
        }
        .predicate a {
            display: inline-block;
            background-color: black;
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .predicate a:hover {
            background-color: #333;
        }
        .object {
            margin-left: 20px;
            position: relative;
        }
        .object a {
            color: black;
            text-decoration: none;
            padding-left: 20px; /* Space for arrow */
            display: inline-block;
        }
        .object a:before {
            content: "▶";
            position: absolute;
            left: 0;
            top: 0;
            font-weight: bold;
            color: black;
        }
        a:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .input-container {
                flex-direction: column;
            }
            .input-container input, .input-container button {
                margin-right: 0;
                width: 100%;
            }
        }
    </style>
    <script>
        function handleLinkClick(event, uri, baseUri) {
            if (uri.startsWith(baseUri)) {
                event.preventDefault(); // Prevent default behavior for internal links
                document.getElementById('uriInput').value = uri; // Set the URI input value
                document.getElementById('sparqlForm').submit(); // Submit the form
            } else {
                event.target.setAttribute('target', '_blank'); // Open external links in a new tab
            }
        }
    </script>
</head>
<body>
    <?php
    $subjectUri = isset($_POST['uri']) ? htmlspecialchars($_POST['uri']) : '';
    $endpointUrl = isset($_POST['endpoint']) ? htmlspecialchars($_POST['endpoint']) : '';
    $username = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
    $password = isset($_POST['password']) ? htmlspecialchars($_POST['password']) : '';

    // Extract the base URI
    $parsedUrl = parse_url($subjectUri);
    $baseUri = isset($parsedUrl['scheme']) && isset($parsedUrl['host']) ? $parsedUrl['scheme'] . '://' . $parsedUrl['host'] : '';

    ?>
    <form id="sparqlForm" method="POST">
        <div class="input-container">
            <input type="text" id="uriInput" name="uri" placeholder="URI (leave blank to explore)" value="<?php echo $subjectUri; ?>">
            <input type="text" name="endpoint" placeholder="Endpoint (required)" value="<?php echo $endpointUrl; ?>" required>
            <input type="text" name="username" placeholder="Username" value="<?php echo $username; ?>">
            <input type="password" name="password" placeholder="Password" value="<?php echo $password; ?>">
            <button type="submit">Look!</button>
        </div>
    </form>

    <div class="results">
        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($endpointUrl)) {
            if (empty($subjectUri)) {
                // SPARQL query when URI is not provided
                $sparqlQuery = '
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
PREFIX dcat: <http://www.w3.org/ns/dcat#>
PREFIX dcterms: <http://purl.org/dc/terms/>
PREFIX foaf: <http://xmlns.com/foaf/0.1/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>

SELECT ?subject ?predicate ?object WHERE {
  {
    # Check for SKOS ConceptScheme
    ?object ?p skos:ConceptScheme.
    FILTER(isIRI(?object))
    BIND(IRI("https://example.com/initialExplore") AS ?subject)
    BIND(IRI("https://example.com/possibleEntryPoint") AS ?predicate)
  } UNION {
    # Fallback to identifying datasets or catalogs
    ?object rdf:type dcat:Dataset.
    FILTER(isIRI(?object))
    BIND(IRI("https://example.com/initialExploreDataset") AS ?subject)
    BIND(IRI("https://example.com/possibleDataset") AS ?predicate)
  } UNION {
    # Fallback to identifying people or organizations
    ?object rdf:type foaf:Person.
    FILTER(isIRI(?object))
    BIND(IRI("https://example.com/initialExplorePerson") AS ?subject)
    BIND(IRI("https://example.com/possiblePerson") AS ?predicate)
  } UNION {
    # Fallback to identifying collections or bibliographic resources
    ?object rdf:type dcterms:Collection.
    FILTER(isIRI(?object))
    BIND(IRI("https://example.com/initialExploreCollection") AS ?subject)
    BIND(IRI("https://example.com/possibleCollection") AS ?predicate)
  } UNION {
    # Fallback to identifying highly connected nodes (example threshold)
    {
      SELECT ?object (COUNT(?s) AS ?inDegree) WHERE {
        ?s ?p ?object.
        FILTER(isIRI(?object))
      }
      GROUP BY ?object
      HAVING (COUNT(?s) > 150) # Arbitrary threshold for highly connected
    }
    BIND(IRI("https://example.com/highlyConnected") AS ?subject)
    BIND(IRI("https://example.com/highConnection") AS ?predicate)
  }
}
                ';
            } else {
                // SPARQL query with the subject URI
                $sparqlQuery = '
                PREFIX dcterms:<http://purl.org/dc/terms/>
                PREFIX skos:<http://www.w3.org/2004/02/skos/core#>
                PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
                PREFIX rdfs:<http://www.w3.org/2000/01/rdf-schema#>

                SELECT ?subject ?predicate (COALESCE(?formattedO, str(?originalO)) AS ?object) (GROUP_CONCAT(DISTINCT CONCAT(?label, " (", ?lang, ")") ; separator=", ") AS ?objectLabel) WHERE {
                  VALUES ?subject { <' . $subjectUri . '> }
                  ?subject ?predicate ?originalO
                  OPTIONAL {
                    {
                      ?originalO skos:prefLabel ?label .
                    } UNION {
                      ?originalO dcterms:title ?label .
                    } UNION {
                      ?originalO rdfs:label ?label .
                    }
                    BIND (lang(?label) AS ?lang)
                  }
                  BIND (IF(isLiteral(?originalO) && lang(?originalO) != "", CONCAT(str(?originalO), " (", lang(?originalO), ")"), str(?originalO)) AS ?formattedO)
                }
                GROUP BY ?subject ?predicate ?originalO ?formattedO
                ';
            }

            // Function to execute the SPARQL query
            function executeSparqlQuery($endpointUrl, $sparqlQuery, $username, $password) {
                $url = $endpointUrl . "?query=" . urlencode($sparqlQuery);
                $headers = [
                    "Accept: application/sparql-results+json"
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                // Set authentication if provided
                if (!empty($username) && !empty($password)) {
                    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
                }

                $response = curl_exec($ch);
                curl_close($ch);

                return json_decode($response, true);
            }

            // Function to extract the display text from a URI
            function extractDisplayText($uri) {
                $parts = explode('#', $uri);
                if (count($parts) > 1) {
                    return end($parts);
                } else {
                    $parts = explode('/', $uri);
                    return end($parts);
                }
            }

            // Execute the query and get results
            $results = executeSparqlQuery($endpointUrl, $sparqlQuery, $username, $password);

            // Group results by predicate
            $groupedResults = [];
            if (isset($results['results']['bindings']) && count($results['results']['bindings']) > 0) {
                foreach ($results['results']['bindings'] as $result) {
                    $subject = isset($result['subject']['value']) ? $result['subject']['value'] : '';
                    $predicate = isset($result['predicate']['value']) ? $result['predicate']['value'] : '';
                    $object = isset($result['object']['value']) ? $result['object']['value'] : '';
                    $objectLabel = isset($result['objectLabel']['value']) ? $result['objectLabel']['value'] : '';

                    if (!isset($groupedResults[$predicate])) {
                        $groupedResults[$predicate] = [];
                    }

                    $groupedResults[$predicate][] = [
                        'subject' => $subject,
                        'object' => $object,
                        'objectLabel' => $objectLabel,
                    ];
                }
            }

            // Display grouped results
            if (!empty($groupedResults)) {
                foreach ($groupedResults as $predicate => $objects) {
                    $predicateDisplayText = extractDisplayText($predicate);
                    echo "<div class='predicate'><a href=\"" . htmlspecialchars($predicate) . "\" target=\"_blank\">" . htmlspecialchars($predicateDisplayText) . "</a></div>";

                    foreach ($objects as $objectData) {
                        $subject = $objectData['subject'];
                        $object = $objectData['object'];
                        $objectLabel = $objectData['objectLabel'];
                        $objectDisplayText = $objectLabel ? $objectLabel : $object;

                        if (filter_var($object, FILTER_VALIDATE_URL)) {
                            echo "<div class='object'><a href=\"" . htmlspecialchars($object) . "\" onclick=\"handleLinkClick(event, '" . htmlspecialchars($object) . "', '" . $baseUri . "')\">" . htmlspecialchars($objectDisplayText) . "</a></div>";
                        } else {
                            echo "<div class='object'>" . htmlspecialchars($objectDisplayText) . "</div>";
                        }
                    }
                }
            } else {
                echo "No results found.";
            }
        }
        ?>
    </div>
</body>
</html>
