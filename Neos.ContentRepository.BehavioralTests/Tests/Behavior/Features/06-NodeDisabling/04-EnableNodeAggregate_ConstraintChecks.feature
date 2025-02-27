@contentrepository @adapters=DoctrineDBAL,Postgres
Feature: Enable a node aggregate

  As a user of the CR I want to enable a disabled node and expect its descendants that have been directly disabled by this node to become enabled as well.

  These are the base test cases for the NodeAggregateCommandHandler to block invalid commands.

  Background:
    Given using the following content dimensions:
      | Identifier | Values      | Generalizations |
      | language   | de, gsw, en | gsw->de, en     |
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:Document': []
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
      | nodeAggregateId        | nodeTypeName                            | parentNodeAggregateId  | nodeName |
      | sir-david-nodenborough | Neos.ContentRepository.Testing:Document | lady-eleonode-rootford | document |

  Scenario: Try to enable a node aggregate in a non-existing content stream
    When the command EnableNodeAggregate is executed with payload and exceptions are caught:
      | Key                          | Value                    |
      | workspaceName                | "i-do-not-exist"         |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | nodeVariantSelectionStrategy | "allVariants"            |
    Then the last command should have thrown an exception of type "WorkspaceDoesNotExist"

  Scenario: Try to enable a non-existing node aggregate
    When the command EnableNodeAggregate is executed with payload and exceptions are caught:
      | Key                          | Value            |
      | nodeAggregateId              | "i-do-not-exist" |
      | nodeVariantSelectionStrategy | "allVariants"    |
    Then the last command should have thrown an exception of type "NodeAggregateCurrentlyDoesNotExist"

  Scenario: Try to enable an already enabled node aggregate
    When the command EnableNodeAggregate is executed with payload and exceptions are caught:
      | Key                          | Value                    |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | nodeVariantSelectionStrategy | "allVariants"            |
    Then the last command should have thrown an exception of type "NodeAggregateIsAlreadyEnabled"

  Scenario: Try to enable a node aggregate in a non-existing dimension space point
    When the command EnableNodeAggregate is executed with payload and exceptions are caught:
      | Key                          | Value                       |
      | nodeAggregateId              | "sir-david-nodenborough"    |
      | coveredDimensionSpacePoint   | {"undeclared": "undefined"} |
      | nodeVariantSelectionStrategy | "allVariants"               |
    Then the last command should have thrown an exception of type "DimensionSpacePointNotFound"

  Scenario: Try to disable a node aggregate in a dimension space point it does not cover
    When the command EnableNodeAggregate is executed with payload and exceptions are caught:
      | Key                          | Value                    |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | coveredDimensionSpacePoint   | {"language": "en"}       |
      | nodeVariantSelectionStrategy | "allVariants"            |
    Then the last command should have thrown an exception of type "NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint"
