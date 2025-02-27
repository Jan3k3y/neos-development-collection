@contentrepository
Feature: Run integrity violation detection regarding reference relations

  As a user of the CR I want to know whether there are disconnected reference relations

  Background:
    Given using the following content dimensions:
      | Identifier | Values      | Generalizations |
      | language   | de, gsw, fr | gsw->de         |
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:Document':
      properties:
        referenceProperty:
          type: reference
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And the command CreateRootWorkspace is executed with payload:
      | Key                  | Value                |
      | workspaceName        | "live"               |
      | newContentStreamId   | "cs-identifier"      |
    And I am in workspace "live" and dimension space point {"language":"de"}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-rootford"      |
      | nodeTypeName    | "Neos.ContentRepository:Root" |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                    |
      | workspaceName               | "live"                                                   |
      | contentStreamId             | "cs-identifier"                                          |
      | nodeAggregateId             | "source-nodandaise"                                      |
      | nodeTypeName                | "Neos.ContentRepository.Testing:Document"                |
      | originDimensionSpacePoint   | {"language":"de"}                                        |
      | coveredDimensionSpacePoints | [{"language":"de"},{"language":"gsw"},{"language":"fr"}] |
      | parentNodeAggregateId       | "lady-eleonode-rootford"                                 |
      | nodeAggregateClassification | "regular"                                                |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                    |
      | workspaceName               | "live"                                                   |
      | contentStreamId             | "cs-identifier"                                          |
      | nodeAggregateId             | "anthony-destinode"                                      |
      | nodeTypeName                | "Neos.ContentRepository.Testing:Document"                |
      | originDimensionSpacePoint   | {"language":"de"}                                        |
      | coveredDimensionSpacePoints | [{"language":"de"},{"language":"gsw"},{"language":"fr"}] |
      | parentNodeAggregateId       | "lady-eleonode-rootford"                                 |
      | nodeAggregateClassification | "regular"                                                |

  Scenario: Detach a reference relation from its source
    When the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceOriginDimensionSpacePoint | {"language":"de"}                 |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | references                      | [{"referenceName": "referenceProperty", "references": [{"target": "anthony-destinode"}]}] |
    And I detach the following reference relation from its source:
      | Key                        | Value               |
      | contentStreamId            | "cs-identifier"     |
      | sourceNodeAggregateId      | "source-nodandaise" |
      | dimensionSpacePoint        | {"language":"gsw"}  |
      | destinationNodeAggregateId | "anthony-destinode" |
      | referenceName              | "referenceProperty" |
    And I run integrity violation detection
    Then I expect the integrity violation detection result to contain exactly 1 error
    And I expect integrity violation detection result error number 1 to have code 1597919585
