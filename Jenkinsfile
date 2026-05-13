pipeline {
    agent any

    environment {
        AWS_REGION      = 'ap-southeast-5'
        AWS_ACCOUNT_ID  = '573068821721'
        ECR_NAMESPACE   = 'renewngo'
        ECR_REPO        = 'account-receivable'
        ECS_CLUSTER     = 'rng-account-receivable-cluster'
        // ECS_SERVICE     = 'account-receivable-service'
        IMAGE_NAME      = "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_NAMESPACE}/${ECR_REPO}"
        IMAGE_TAG       = "${env.BUILD_NUMBER}"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Login to ECR') {
            steps {
                script {
                    sh "aws ecr get-login-password --region ${AWS_REGION} | docker login --username AWS --password-stdin ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
                }
            }
        }

        stage('Build Docker Image') {
            steps {
                script {
                    sh "docker build --pull --no-cache -t ${IMAGE_NAME}:${IMAGE_TAG} -t ${IMAGE_NAME}:latest -f docker/php/Dockerfile ."
                }
            }
        }

        stage('Push to ECR') {
            steps {
                script {
                    sh "docker push ${IMAGE_NAME}:${IMAGE_TAG}"
                    sh "docker push ${IMAGE_NAME}:latest"
                }
            }
        }

        stage('Clean Up') {
            steps {
                script {
                    sh "docker image prune -af"
                }
            }
        }

        // stage('Deploy to ECS') {
        //     steps {
        //         script {
        //             // Update the ECS service with the new image tag
        //             // Note: This assumes the task definition uses 'latest' or you update the task def separately
        //             sh "aws ecs update-service --cluster ${ECS_CLUSTER} --service ${ECS_SERVICE} --force-new-deployment --region ${AWS_REGION}"
        //         }
        //     }
        // }
    }

    // post {
    //     always {
    //         sh "docker logout ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
    //     }
    //     success {
    //         echo 'Deployment successful!'
    //     }
    //     failure {
    //         echo 'Deployment failed!'
    //     }
    // }
}
