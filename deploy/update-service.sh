# based on ecs-deploy script
TASK_DEFINITION_NAME=$(aws ecs describe-services --services $SERVICE --cluster $CLUSTER | jq -r .services[0].taskDefinition)
TASK_DEFINITION=$(aws ecs describe-task-definition --task-def "$TASK_DEFINITION_NAME" | jq '.taskDefinition')

NEW_CONTAINER_DEFINITIONS=$(echo "$TASK_DEFINITION" | jq --arg NEW_TAG $TAG_PURE 'def replace_tag: if . | test("[a-zA-Z0-9.]+/[a-zA-Z0-9]+:[a-zA-Z0-9]+") then sub("(?<s>[a-zA-Z0-9.]+/[a-zA-Z0-9]+:)[a-zA-Z0-9]+"; "\(.s)" + $NEW_TAG) else . end ; .containerDefinitions | [.[] | .+{image: .image | replace_tag}]')

TASK_DEFINITION=$(echo "$TASK_DEFINITION" | jq ".+{containerDefinitions: $NEW_CONTAINER_DEFINITIONS}")

# Default JQ filter for new task definition
NEW_DEF_JQ_FILTER="family: .family, volumes: .volumes, containerDefinitions: .containerDefinitions, placementConstraints: .placementConstraints"

# Some options in task definition should only be included in new definition if present in
# current definition. If found in current definition, append to JQ filter.
CONDITIONAL_OPTIONS=(networkMode taskRoleArn)
for i in "${CONDITIONAL_OPTIONS[@]}"; do
  re=".*${i}.*"
  if [[ "$TASK_DEFINITION" =~ $re ]]; then
    NEW_DEF_JQ_FILTER="${NEW_DEF_JQ_FILTER}, ${i}: .${i}"
  fi
done

# Build new DEF with jq filter
NEW_DEF=$(echo $TASK_DEFINITION | jq "{${NEW_DEF_JQ_FILTER}}")
NEW_TASKDEF=`aws ecs register-task-definition --cli-input-json "$NEW_DEF" | jq -r .taskDefinition.taskDefinitionArn`

echo "New task definition registered, $NEW_TASKDEF"

aws ecs update-service --cluster $CLUSTER --service $SERVICE --task-definition "$NEW_TASKDEF" > /dev/null

echo "Service updated"
