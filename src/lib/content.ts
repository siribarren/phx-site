import brand from '../data/brand.json';
import capabilities from '../data/capabilities.json';
import group from '../data/group.json';
import methodology from '../data/methodology.json';
import navigation from '../data/navigation.json';
import problems from '../data/problems.json';
import solutions from '../data/solutions.json';

export function getSiteData() {
  return {
    brand,
    navigation,
    problems,
    capabilities,
    solutions,
    methodology,
    group,
  };
}
