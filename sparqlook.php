<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPARQLook</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            color: #00ff00;
            background-color: black;
            margin: 0;
            padding: 20px;
        }
        .input-container {
            display: flex;
            margin-bottom: 20px;
            flex-wrap: wrap;
            border: 1px solid #00ff00;
            padding: 10px;
            border-radius: 5px;
            background-color: #111;
        }
        .input-container input,
        .input-container button {
            padding: 10px;
            border: 1px solid #00ff00;
            background-color: black;
            color: #00ff00;
            font-family: 'Courier New', Courier, monospace;
            margin-right: 10px;
            margin-bottom: 10px;
            flex-grow: 1;
            font-size: 14px;
        }
        #uriInput {
            text-align: left;
        }
        .input-container button {
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .input-container button:disabled {
            cursor: not-allowed;
        }
        .input-container button:hover:not(:disabled) {
            background-color: #00cc00;
        }
        .loading-spinner {
            display: inline-block;
            border: 2px solid rgba(0, 255, 0, 0.3);
            border-radius: 50%;
            border-top: 2px solid #00ff00;
            width: 12px;
            height: 12px;
            animation: spin 1s linear infinite;
            position: absolute;
            right: 10px;
        }
        .loading-ellipsis::after {
            content: '...';
            animation: ellipsis 1.5s infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes ellipsis {
            0% { content: ''; }
            33% { content: '.'; }
            66% { content: '..'; }
            100% { content: '...'; }
        }
        .results {
            margin-top: 20px;
            border: 1px solid #00ff00;
            padding: 10px;
            border-radius: 5px;
            background-color: #111;
        }
        .predicate {
            margin-bottom: 10px;
            margin-top: 10px;
        }
        .predicate a {
            display: inline-block;
            background-color: black;
            color: #00ff00;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #00ff00;
            border-radius: 5px;
            font-family: 'Courier New', Courier, monospace;
        }
        .predicate a:hover {
            background-color: #00cc00;
        }
        .object {
            margin-left: 20px;
            position: relative;
        }
        .object a {
            color: #00ff00;
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
            color: #00ff00;
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
                box-sizing: border-box; /* Ensures padding is included in the width */
            }
        }
    </style>
    <script>
        function handleLinkClick(event, uri, baseUri) {
            if (uri.startsWith(baseUri)) {
                event.preventDefault(); // Prevent default behavior for internal links
                document.getElementById('uriInput').value = uri; // Set the URI input value
                showLoading(); // Trigger the loading state
                document.getElementById('sparqlForm').submit(); // Submit the form
            } else {
                event.target.setAttribute('target', '_blank'); // Open external links in a new tab
            }
        }

        function showLoading() {
            var button = document.getElementById('submitButton');
            button.disabled = true;
            button.innerHTML = 'Looking... <div class="loading-spinner"></div>';
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
    <form id="sparqlForm" method="POST" onsubmit="showLoading()">
        <div class="input-container">
            <input type="text" id="uriInput" name="uri" placeholder="URI (leave blank to explore)" value="<?php echo $subjectUri; ?>">
            <input type="text" name="endpoint" placeholder="Endpoint (required)" value="<?php echo $endpointUrl; ?>" required>
            <input type="text" name="username" placeholder="Username" value="<?php echo $username; ?>" autocomplete="username">
            <input type="password" name="password" placeholder="Password" value="<?php echo $password; ?>" autocomplete="current-password">
            <button type="submit" id="submitButton">Look!</button>
        </div>
    </form>

    <div class="results">
        <?php
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

        // Group results by predicate
        function groupResultsByPredicate($results) {
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
            return $groupedResults;
        }

        // Function to clean up objectLabels in the results
        function cleanObjectLabels(&$groupedResults) {
            foreach ($groupedResults as $predicate => &$objects) {
                foreach ($objects as &$objectData) {
                    if (isset($objectData['objectLabel']) && trim($objectData['objectLabel']) === '()') {
                        $objectData['objectLabel'] = ''; // Clear the empty label
                    }
                }
            }
        }

        if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($endpointUrl)) {
            if (empty($subjectUri)) {
                // Array of SPARQL queries for initial exploration
                $exploreQueries = [
                    // Query to check for SKOS ConceptScheme
                    '
                    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
                    SELECT DISTINCT ?subject ?predicate ?object WHERE {
                      ?object ?p skos:ConceptScheme.
                      FILTER(isIRI(?object))
                      BIND(IRI("https://example.com/initialExplore") AS ?subject)
                      BIND(IRI("https://example.com/possibleEntryPoint") AS ?predicate)
                    }
                    ',
                    // Query to find broadest SKOS concepts
                    '
                    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
                    SELECT DISTINCT ?subject ?predicate ?object WHERE {
                      ?subjectIgnore skos:broader ?object.
                      FILTER NOT EXISTS { ?object skos:broader ?broader }
                      FILTER(isIRI(?object))
                      BIND(IRI("https://example.com/initialExplore") AS ?subject)
                      BIND(IRI("https://example.com/broadestConcepts") AS ?predicate)
                    }
                    ',
                    // Query to find datasets or catalogs
                    '
                    PREFIX dcat: <http://www.w3.org/ns/dcat#>
                    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
                    SELECT DISTINCT ?subject ?predicate ?object WHERE {
                      ?object rdf:type dcat:Dataset.
                      FILTER(isIRI(?object))
                      BIND(IRI("https://example.com/initialExplore") AS ?subject)
                      BIND(IRI("https://example.com/possibleDataset") AS ?predicate)
                    }
                    ',
                    // Query to find people or organizations
                    '
                    PREFIX foaf: <http://xmlns.com/foaf/0.1/>
                    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
                    SELECT DISTINCT ?subject ?predicate ?object WHERE {
                      ?object rdf:type foaf:Person.
                      FILTER(isIRI(?object))
                      BIND(IRI("https://example.com/initialExplore") AS ?subject)
                      BIND(IRI("https://example.com/possiblePerson") AS ?predicate)
                    }
                    ',
                    // Query to find collections or bibliographic resources
                    '
                    PREFIX dcterms: <http://purl.org/dc/terms/>
                    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
                    SELECT DISTINCT ?subject ?predicate ?object WHERE {
                      ?object rdf:type dcterms:Collection.
                      FILTER(isIRI(?object))
                      BIND(IRI("https://example.com/initialExplore") AS ?subject)
                      BIND(IRI("https://example.com/possibleCollection") AS ?predicate)
                    }
                    ',
                    // Query to find highly connected nodes
                    '
                    SELECT ?subject ?predicate ?object WHERE {
                      {
                        SELECT ?object (COUNT(?s) AS ?inDegree) WHERE {
                          ?s ?p ?object.
                          FILTER(isIRI(?object))
                        }
                        GROUP BY ?object
                        HAVING (COUNT(?s) > 150)
                      }
                      BIND(IRI("https://example.com/initialExplore") AS ?subject)
                      BIND(IRI("https://example.com/highlyConnected") AS ?predicate)
                    }
                    ',
                ];

                $groupedResults = [];

                // Execute each query until results are found
                foreach ($exploreQueries as $query) {
                    $results = executeSparqlQuery($endpointUrl, $query, $username, $password);
                    $groupedResults = groupResultsByPredicate($results);
                    cleanObjectLabels($groupedResults); // Clean up objectLabels
                    if (!empty($groupedResults)) {
                        break; // Stop if results are found
                    }
                }
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

                $results = executeSparqlQuery($endpointUrl, $sparqlQuery, $username, $password);
                $groupedResults = groupResultsByPredicate($results);
                cleanObjectLabels($groupedResults); // Clean up objectLabels
            }

			// Function to determine if a string is a valid URL
			function isValidUrl($url) {
				return preg_match('/^((http|https):\/\/[^\s]+?)$/i', $url);
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

                        // Ensure proper encoding and handling of special characters in URIs
                        if (isValidUrl($object)) {
                            echo "<div class='object'><a href=\"" . htmlspecialchars($object, ENT_QUOTES, 'UTF-8') . "\" onclick=\"handleLinkClick(event, '" . htmlspecialchars($object, ENT_QUOTES, 'UTF-8') . "', '" . $baseUri . "')\">" . htmlspecialchars($objectDisplayText, ENT_QUOTES, 'UTF-8') . "</a></div>";
                        } else {
                            echo "<div class='object'>" . htmlspecialchars($objectDisplayText, ENT_QUOTES, 'UTF-8') . "</div>";
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
