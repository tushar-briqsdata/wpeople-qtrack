#!/usr/bin/env bash

pull_ecr_image(){
	eval $(aws ecr get-login --no-include-email --region us-east-1)
	docker pull 133013689155.dkr.ecr.us-east-1.amazonaws.com/php7.0-apache2:latest
}

pull_ecr_image
