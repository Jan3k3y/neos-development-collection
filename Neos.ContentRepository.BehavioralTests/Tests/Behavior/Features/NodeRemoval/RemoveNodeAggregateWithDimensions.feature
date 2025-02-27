@contentrepository @adapters=DoctrineDBAL
Feature: Remove NodeAggregate

  As a user of the CR I want to be able to remove a NodeAggregate completely.

  Background:
    Given using the following content dimensions:
      | Identifier | Values  | Generalizations |
      | language   | de, gsw | gsw->de         |
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:Document': []
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And the command CreateRootWorkspace is executed with payload:
      | Key                  | Value                |
      | workspaceName        | "live"               |
      | newContentStreamId   | "live-cs-identifier" |
    And I am in workspace "live" and dimension space point {"language":"de"}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-nodesworth"    |
      | nodeTypeName    | "Neos.ContentRepository:Root" |
    # We have to add another node since root nodes are in all dimension space points and thus cannot be varied
    # Node /document
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                   | Value                                     |
      | nodeAggregateId       | "nody-mc-nodeface"                        |
      | nodeTypeName          | "Neos.ContentRepository.Testing:Document" |
      | parentNodeAggregateId | "lady-eleonode-nodesworth"                |
      | nodeName              | "document"                                |
    # We also want to add a child node to make sure it is correctly removed when the parent is removed
    # Node /document/child-document
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                   | Value                                     |
      | nodeAggregateId       | "nodimus-prime"                           |
      | nodeTypeName          | "Neos.ContentRepository.Testing:Document" |
      | parentNodeAggregateId | "nody-mc-nodeface"                        |
      | nodeName              | "child-document"                          |
    And the command CreateNodeVariant is executed with payload:
      | Key             | Value              |
      | nodeAggregateId | "nody-mc-nodeface" |
      | sourceOrigin    | {"language":"de"}  |
      | targetOrigin    | {"language":"gsw"} |

  ########################
  # Section: EXTRA testcases
  ########################
  Scenario: In LIVE workspace, removing a NodeAggregate removes all nodes completely
    When the command RemoveNodeAggregate is executed with payload:
      | Key                          | Value              |
      | nodeAggregateId              | "nody-mc-nodeface" |
      | nodeVariantSelectionStrategy | "allVariants"      |
      | coveredDimensionSpacePoint   | {"language":"de"}  |

    Then I expect the graph projection to consist of exactly 1 node
    And I expect a node identified by live-cs-identifier;lady-eleonode-nodesworth;{} to exist in the content graph

    When I am in workspace "live" and dimension space point {"language":"de"}
    Then I expect the subgraph projection to consist of exactly 1 nodes
    And I expect node aggregate identifier "lady-eleonode-nodesworth" to lead to node live-cs-identifier;lady-eleonode-nodesworth;{}

    When I am in workspace "live" and dimension space point {"language":"gsw"}
    Then I expect the subgraph projection to consist of exactly 1 nodes
    And I expect node aggregate identifier "lady-eleonode-nodesworth" to lead to node live-cs-identifier;lady-eleonode-nodesworth;{}

  Scenario: In USER workspace, removing a NodeAggregate removes all nodes completely; leaving the live workspace untouched

    # Create user workspace
    When the command CreateWorkspace is executed with payload:
      | Key                | Value                |
      | workspaceName      | "user-test"          |
      | baseWorkspaceName  | "live"               |
      | newContentStreamId | "user-cs-identifier" |
    And I am in workspace "user-test"

    When the command RemoveNodeAggregate is executed with payload:
      | Key                          | Value              |
      | nodeAggregateId              | "nody-mc-nodeface" |
      | nodeVariantSelectionStrategy | "allVariants"      |
      | coveredDimensionSpacePoint   | {"language":"de"}  |

    Then I expect the graph projection to consist of exactly 4 nodes
    And I expect a node identified by live-cs-identifier;lady-eleonode-nodesworth;{} to exist in the content graph
    And I expect a node identified by live-cs-identifier;nody-mc-nodeface;{"language":"de"} to exist in the content graph
    And I expect a node identified by live-cs-identifier;nody-mc-nodeface;{"language":"gsw"} to exist in the content graph
    And I expect a node identified by live-cs-identifier;nodimus-prime;{"language":"de"} to exist in the content graph

    When I am in workspace "user-test" and dimension space point {"language":"de"}
    Then I expect the subgraph projection to consist of exactly 1 nodes
    And I expect node aggregate identifier "lady-eleonode-nodesworth" to lead to node user-cs-identifier;lady-eleonode-nodesworth;{}

    When I am in workspace "user-test" and dimension space point {"language":"gsw"}
    Then I expect the subgraph projection to consist of exactly 1 nodes
    And I expect node aggregate identifier "lady-eleonode-nodesworth" to lead to node user-cs-identifier;lady-eleonode-nodesworth;{}

    # ensure LIVE ContentStream is untouched
    When I am in workspace "live" and dimension space point {"language":"de"}
    Then I expect the subgraph projection to consist of exactly 3 nodes
    And I expect node aggregate identifier "lady-eleonode-nodesworth" to lead to node live-cs-identifier;lady-eleonode-nodesworth;{}
    And I expect node aggregate identifier "nody-mc-nodeface" and node path "document" to lead to node live-cs-identifier;nody-mc-nodeface;{"language":"de"}
    And I expect node aggregate identifier "nodimus-prime" and node path "document/child-document" to lead to node live-cs-identifier;nodimus-prime;{"language":"de"}

    When I am in workspace "live" and dimension space point {"language":"gsw"}
    Then I expect the subgraph projection to consist of exactly 3 nodes
    And I expect node aggregate identifier "lady-eleonode-nodesworth" to lead to node live-cs-identifier;lady-eleonode-nodesworth;{}
    And I expect node aggregate identifier "nody-mc-nodeface" and node path "document" to lead to node live-cs-identifier;nody-mc-nodeface;{"language":"gsw"}
    And I expect node aggregate identifier "nodimus-prime" and node path "document/child-document" to lead to node live-cs-identifier;nodimus-prime;{"language":"de"}
