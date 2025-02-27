@contentrepository @adapters=DoctrineDBAL
Feature: On forking a content stream, node references should be copied as well.

  Because we store reference node information in an extra DB table, this needs to be copied correctly on ForkContentStream
  as well.

  Background:
    Given using the following content dimensions:
      | Identifier | Values          | Generalizations      |
      | language   | mul, de, en, ch | ch->de->mul, en->mul |
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:NodeWithReferences':
      properties:
        referenceProperty:
          type: reference
        referencesProperty:
          type: references
        text:
          type: string
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And I am user identified by "initiating-user-identifier"
    And the command CreateRootWorkspace is executed with payload:
      | Key                  | Value                |
      | workspaceName        | "live"               |
      | newContentStreamId   | "cs-identifier"      |
    And I am in workspace "live" and dimension space point {"language":"de"}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-rootford"      |
      | nodeTypeName    | "Neos.ContentRepository:Root" |
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId   | nodeTypeName                                      | parentNodeAggregateId  |
      | source-nodandaise | Neos.ContentRepository.Testing:NodeWithReferences | lady-eleonode-rootford |
      | anthony-destinode | Neos.ContentRepository.Testing:NodeWithReferences | lady-eleonode-rootford |

  Scenario: Create a reference, trigger copy-on-write of the nodes, and ensure reference still exists.
    Given the command SetNodeReferences is executed with payload:
      | Key                   | Value                             |
      | sourceNodeAggregateId | "source-nodandaise"               |
      | references            | [{"referenceName": "referenceProperty", "references": [{"target": "anthony-destinode"}]}] |

    # Uses ForkContentStream implicitly
    When the command CreateWorkspace is executed with payload:
      | Key                | Value                |
      | baseWorkspaceName  | "live"               |
      | workspaceName      | "user-test"          |
      | newContentStreamId | "user-cs-identifier" |

    # after forking, the reference must still exist on the forked content stream (no surprises here).
    When I am in workspace "user-test" and dimension space point {"language": "de"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node user-cs-identifier;source-nodandaise;{"language": "de"}
    Then I expect this node to have the following references:
      | Name              | Node                                                    | Properties |
      | referenceProperty | user-cs-identifier;anthony-destinode;{"language": "de"} | null       |
    Then I expect node aggregate identifier "anthony-destinode" to lead to node user-cs-identifier;anthony-destinode;{"language": "de"}
    And I expect this node to be referenced by:
      | Name              | Node                                                    | Properties |
      | referenceProperty | user-cs-identifier;source-nodandaise;{"language": "de"} | null       |

    When I am in workspace "user-test" and dimension space point {"language": "ch"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node user-cs-identifier;source-nodandaise;{"language": "de"}
    Then I expect this node to have the following references:
      | Name              | Node                                                    | Properties |
      | referenceProperty | user-cs-identifier;anthony-destinode;{"language": "de"} | null       |
    Then I expect node aggregate identifier "anthony-destinode" to lead to node user-cs-identifier;anthony-destinode;{"language": "de"}
    And I expect this node to be referenced by:
      | Name              | Node                                                    | Properties |
      | referenceProperty | user-cs-identifier;source-nodandaise;{"language": "de"} | null       |

    # after then modifying the node's properties (thus triggering copy-on-write), the reference property
    # should still exist (this was a BUG)
    When I am in workspace "user-test" and dimension space point {"language": "de"}
    And the command SetNodeProperties is executed with payload:
      | Key             | Value                                  |
      | nodeAggregateId | "source-nodandaise"                    |
      | propertyValues  | {"text": "Modified in live workspace"} |
    Then I expect node aggregate identifier "source-nodandaise" to lead to node user-cs-identifier;source-nodandaise;{"language": "de"}
    And I expect this node to have the following references:
      | Name              | Node                                                    | Properties |
      | referenceProperty | user-cs-identifier;anthony-destinode;{"language": "de"} | null       |
    Then I expect node aggregate identifier "anthony-destinode" to lead to node user-cs-identifier;anthony-destinode;{"language": "de"}
    And I expect this node to be referenced by:
      | Name              | Node                                                    | Properties |
      | referenceProperty | user-cs-identifier;source-nodandaise;{"language": "de"} | null       |

    When I am in workspace "user-test" and dimension space point {"language": "ch"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node user-cs-identifier;source-nodandaise;{"language": "de"}
    And I expect this node to have the following references:
      | Name              | Node                                                    | Properties |
      | referenceProperty | user-cs-identifier;anthony-destinode;{"language": "de"} | null       |
    Then I expect node aggregate identifier "anthony-destinode" to lead to node user-cs-identifier;anthony-destinode;{"language": "de"}
    And I expect this node to be referenced by:
      | Name              | Node                                                    | Properties |
      | referenceProperty | user-cs-identifier;source-nodandaise;{"language": "de"} | null       |
