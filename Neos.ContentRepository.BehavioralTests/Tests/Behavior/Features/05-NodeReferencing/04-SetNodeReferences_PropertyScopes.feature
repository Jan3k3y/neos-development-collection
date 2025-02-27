@contentrepository @adapters=DoctrineDBAL,Postgres
Feature: Set node properties with different scopes

  As a user of the CR I want to modify node references with different scopes.

  Background:
    Given using the following content dimensions:
      | Identifier | Values       | Generalizations |
      | language   | mul, de, gsw | gsw->de->mul    |
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:NodeWithReferences':
      properties:
        unscopedReference:
          type: reference
        unscopedReferences:
          type: references
        nodeScopedReference:
          type: reference
          scope: node
        nodeScopedReferences:
          type: references
          scope: node
        nodeAggregateScopedReference:
          type: reference
          scope: nodeAggregate
        nodeAggregateScopedReferences:
          type: references
          scope: nodeAggregate
        specializationsScopedReference:
          type: reference
          scope: specializations
        specializationsScopedReferences:
          type: references
          scope: specializations
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And I am user identified by "initiating-user-identifier"
    And the command CreateRootWorkspace is executed with payload:
      | Key                  | Value                |
      | workspaceName        | "live"               |
      | newContentStreamId   | "cs-identifier"      |
    And I am in workspace "live" and dimension space point {"language":"mul"}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-rootford"      |
      | nodeTypeName    | "Neos.ContentRepository:Root" |
    # We have to add another node since root nodes have no dimension space points and thus cannot be varied
    # Node /document
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId   | parentNodeAggregateId  | nodeTypeName                                      |
      | source-nodandaise | lady-eleonode-rootford | Neos.ContentRepository.Testing:NodeWithReferences |
      | anthony-destinode | lady-eleonode-rootford | Neos.ContentRepository.Testing:NodeWithReferences |
    And the command CreateNodeVariant is executed with payload:
      | Key             | Value               |
      | nodeAggregateId | "source-nodandaise" |
      | sourceOrigin    | {"language":"mul"}  |
      | targetOrigin    | {"language":"de"}   |
    And the command CreateNodeVariant is executed with payload:
      | Key             | Value               |
      | nodeAggregateId | "source-nodandaise" |
      | sourceOrigin    | {"language":"mul"}  |
      | targetOrigin    | {"language":"gsw"}  |

  Scenario: Set node references in separate commands
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                |
      | references                      | [{"referenceName": "unscopedReference", "references": [{"target": "anthony-destinode"}]}] |
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                |
      | references                      | [{"referenceName": "unscopedReferences", "references": [{"target": "anthony-destinode"}]}] |
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                |
      | references                      | [{"referenceName": "nodeScopedReference", "references": [{"target": "anthony-destinode"}]}] |
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                |
      | references                      | [{"referenceName": "nodeScopedReferences", "references": [{"target": "anthony-destinode"}]}] |
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                |
      | references                      | [{"referenceName": "nodeAggregateScopedReference", "references": [{"target": "anthony-destinode"}]}] |
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                |
      | references                      | [{"referenceName": "nodeAggregateScopedReferences", "references": [{"target": "anthony-destinode"}]}] |
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                |
      | references                      | [{"referenceName": "specializationsScopedReference", "references": [{"target": "anthony-destinode"}]}] |
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                             |
      | sourceNodeAggregateId           | "source-nodandaise"               |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                |
      | references                      | [{"referenceName": "specializationsScopedReferences", "references": [{"target": "anthony-destinode"}]}] |

    When I am in workspace "live" and dimension space point {"language": "mul"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "mul"}
    And I expect this node to have the following references:
      | Name                          | Node                                                | Properties |
      | nodeAggregateScopedReference  | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeAggregateScopedReferences | cs-identifier;anthony-destinode;{"language": "mul"} | null       |

    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Name                          | Node                                                | Properties |
      | nodeAggregateScopedReference  | cs-identifier;source-nodandaise;{"language": "mul"} | null       |
      | nodeAggregateScopedReferences | cs-identifier;source-nodandaise;{"language": "mul"} | null       |

    When I am in workspace "live" and dimension space point {"language": "de"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "de"}
    And I expect this node to have the following references:
      | Name                            | Node                                                | Properties |
      | nodeAggregateScopedReference    | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeAggregateScopedReferences   | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeScopedReference             | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeScopedReferences            | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | specializationsScopedReference  | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | specializationsScopedReferences | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | unscopedReference               | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | unscopedReferences              | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Name                            | Node                                               | Properties |
      | nodeAggregateScopedReference    | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | nodeAggregateScopedReferences   | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | nodeScopedReference             | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | nodeScopedReferences            | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | specializationsScopedReference  | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | specializationsScopedReferences | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | unscopedReference               | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | unscopedReferences              | cs-identifier;source-nodandaise;{"language": "de"} | null       |

    When I am in workspace "live" and dimension space point {"language": "gsw"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "gsw"}
    And I expect this node to have the following references:
      | Name                            | Node                                                | Properties |
      | nodeAggregateScopedReference    | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeAggregateScopedReferences   | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | specializationsScopedReference  | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | specializationsScopedReferences | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Name                            | Node                                                | Properties |
      | nodeAggregateScopedReference    | cs-identifier;source-nodandaise;{"language": "gsw"} | null       |
      | nodeAggregateScopedReferences   | cs-identifier;source-nodandaise;{"language": "gsw"} | null       |
      | specializationsScopedReference  | cs-identifier;source-nodandaise;{"language": "gsw"} | null       |
      | specializationsScopedReferences | cs-identifier;source-nodandaise;{"language": "gsw"} | null       |


  Scenario: Set node properties in single command
    And the command SetNodeReferences is executed with payload:
      | Key                             | Value                                                    |
      | sourceNodeAggregateId           | "source-nodandaise"                                      |
      | sourceOriginDimensionSpacePoint | {"language": "de"}                                       |
      | references                      | [{"referenceName": "unscopedReference", "references": [{"target": "anthony-destinode"}]}, {"referenceName": "unscopedReferences", "references": [{"target": "anthony-destinode"}]}, {"referenceName": "nodeScopedReference", "references": [{"target": "anthony-destinode"}]}, {"referenceName": "nodeScopedReferences", "references": [{"target": "anthony-destinode"}]}, {"referenceName": "nodeAggregateScopedReference", "references": [{"target": "anthony-destinode"}]}, {"referenceName": "nodeAggregateScopedReferences", "references": [{"target": "anthony-destinode"}]}, {"referenceName": "specializationsScopedReference", "references": [{"target": "anthony-destinode"}]}, {"referenceName": "specializationsScopedReferences", "references": [{"target": "anthony-destinode"}]}]|

    When I am in workspace "live" and dimension space point {"language": "mul"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "mul"}
    And I expect this node to have the following references:
      | Name                          | Node                                                | Properties |
      | nodeAggregateScopedReference  | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeAggregateScopedReferences | cs-identifier;anthony-destinode;{"language": "mul"} | null       |

    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Name                          | Node                                                | Properties |
      | nodeAggregateScopedReference  | cs-identifier;source-nodandaise;{"language": "mul"} | null       |
      | nodeAggregateScopedReferences | cs-identifier;source-nodandaise;{"language": "mul"} | null       |

    When I am in workspace "live" and dimension space point {"language": "de"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "de"}
    And I expect this node to have the following references:
      | Name                            | Node                                                | Properties |
      | nodeAggregateScopedReference    | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeAggregateScopedReferences   | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeScopedReference             | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeScopedReferences            | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | specializationsScopedReference  | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | specializationsScopedReferences | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | unscopedReference               | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | unscopedReferences              | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Name                            | Node                                               | Properties |
      | nodeAggregateScopedReference    | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | nodeAggregateScopedReferences   | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | nodeScopedReference             | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | nodeScopedReferences            | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | specializationsScopedReference  | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | specializationsScopedReferences | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | unscopedReference               | cs-identifier;source-nodandaise;{"language": "de"} | null       |
      | unscopedReferences              | cs-identifier;source-nodandaise;{"language": "de"} | null       |

    When I am in workspace "live" and dimension space point {"language": "gsw"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "gsw"}
    And I expect this node to have the following references:
      | Name                            | Node                                                | Properties |
      | nodeAggregateScopedReference    | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | nodeAggregateScopedReferences   | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | specializationsScopedReference  | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
      | specializationsScopedReferences | cs-identifier;anthony-destinode;{"language": "mul"} | null       |
    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Name                            | Node                                                | Properties |
      | nodeAggregateScopedReference    | cs-identifier;source-nodandaise;{"language": "gsw"} | null       |
      | nodeAggregateScopedReferences   | cs-identifier;source-nodandaise;{"language": "gsw"} | null       |
      | specializationsScopedReference  | cs-identifier;source-nodandaise;{"language": "gsw"} | null       |
      | specializationsScopedReferences | cs-identifier;source-nodandaise;{"language": "gsw"} | null       |
