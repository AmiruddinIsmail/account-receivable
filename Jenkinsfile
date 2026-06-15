pipeline {
    agent any

    environment {
        AWS_REGION      = 'ap-southeast-5'
        AWS_ACCOUNT_ID  = '573068821721'
        ECR_NAMESPACE   = 'renewngo'
        ECR_REPO        = 'account-receivable'
        IMAGE_NAME      = "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_NAMESPACE}/${ECR_REPO}"
        IMAGE_TAG       = "${env.BUILD_NUMBER}"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Prepare') {
            steps {
                script {
                    env.IMAGE_TAG = sh(
                        script: 'git rev-parse --short HEAD',
                        returnStdout: true
                    ).trim()
                }
            }
        }

        stage('Setup Buildx') {
            steps {
                sh '''
                    docker buildx create --use --name builder || true
                    docker buildx inspect --bootstrap
                '''
            }
        }

        stage('Login to ECR') {
            steps {
                script {
                    sh "aws ecr get-login-password --region ${AWS_REGION} | docker login --username AWS --password-stdin ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
                }
            }
        }

        stage('Build & Push') {
            steps {
                sh """
                    docker buildx build \
                        --platform linux/amd64 \
                        --pull \
                        --cache-from type=registry,ref=${IMAGE_NAME}:buildcache \
                        --cache-to type=registry,ref=${IMAGE_NAME}:buildcache,mode=max \
                        -t ${IMAGE_NAME}:${IMAGE_TAG} \
                        -t ${IMAGE_NAME}:latest \
                        -f docker/php/Dockerfile \
                        . \
                        --push
                """
            }
        }        
    }

    post {
        always {
            sh "docker logout ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com || true"
        }
    }
}
