#!/usr/bin/env bash

# Create a deploy.zip from the www directory.

rm -rf deploy.zip
cd www
zip -r9 ../deploy.zip *
