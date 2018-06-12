#!/usr/bin/env bash

push_ecr_image(){
	eval $(aws ecr get-login --region us-east-1)
	docker tag $ECS_IMAGE:latest $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/$ECS_IMAGE:latest
	docker push $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/$ECS_IMAGE:latest
}

push_ecr_image
