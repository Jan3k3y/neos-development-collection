@contentrepository @adapters=DoctrineDBAL
@flowEntities
Feature: Disable node without dimensions

  Background: Create node aggregate with initial node
    Given using no content dimensions
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:Node':
      properties:
        text:
          type: string

    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And the command CreateRootWorkspace is executed with payload:
      | Key                  | Value                |
      | workspaceName        | "live"               |
      | workspaceTitle       | "Live"               |
      | workspaceDescription | "The live workspace" |
      | newContentStreamId   | "cs-identifier"      |

    And I am in workspace "live"
    And I am in dimension space point {}

    And I am user identified by "initiating-user-identifier"
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-rootford"      |
      | nodeTypeName    | "Neos.ContentRepository:Root" |

    Then the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId           | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                      |
      | sir-david-nodenborough    | node       | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                                                         |
      | nody-mc-nodeface          | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"}          |
      | sir-nodeward-nodington-iv | bakura     | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington IV"} |


    Then the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |
    And I am in workspace "user-workspace"
    And I am in dimension space point {}

  Scenario: Disable a node
    Given the command DisableNodeAggregate is executed with payload:
      | Key                          | Value                    |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | coveredDimensionSpacePoint   | {}                       |
      | nodeVariantSelectionStrategy | "allVariants"            |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId        | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough | 0       | 1       | 0     | 0       | {}                        |
    And I expect the ChangeProjection to have no changes in "cs-identifier"
